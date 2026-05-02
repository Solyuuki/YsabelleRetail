#!/usr/bin/env python
from __future__ import annotations

import argparse
import json
import math
import os
import sys
from dataclasses import dataclass
from typing import Any

import numpy as np
import torch
from PIL import Image, ImageOps
from transformers import AutoProcessor, CLIPModel


POSITIVE_PROMPTS = [
    "a product photo of a sneaker",
    "a product photo of a running shoe",
    "an athletic shoe on a clean background",
    "a sneaker worn on foot in a real-world photo",
    "a casual photo of footwear with background clutter",
    "a close-up photo of a shoe taken on a phone camera",
]

NEGATIVE_PROMPTS = [
    "a plate of food",
    "a portrait of a person",
    "a company logo",
    "a random household object",
    "an abstract icon",
]


@dataclass
class PreparedImage:
    crop_name: str
    image: Image.Image


class ClipEmbeddingService:
    def __init__(self, model_name: str, embedding_version: str, image_size: int = 224) -> None:
        self.model_name = model_name
        self.embedding_version = embedding_version
        self.image_size = image_size
        self.device = "cuda" if torch.cuda.is_available() else "cpu"
        self.processor = AutoProcessor.from_pretrained(model_name)
        self.model = CLIPModel.from_pretrained(model_name)
        self.model.eval()
        self.model.to(self.device)
        self._text_features = self._build_text_features()

    def health(self) -> dict[str, Any]:
        return {
            "ok": True,
            "backend": "transformers",
            "service": "clip-image-embedding",
            "model": self.model_name,
            "embedding_version": self.embedding_version,
            "device": self.device,
        }

    def embed_path(self, path: str) -> dict[str, Any]:
        return self.embed_batch([{"id": "upload", "path": path}])["results"][0]

    def embed_batch(self, items: list[dict[str, Any]]) -> dict[str, Any]:
        results: list[dict[str, Any]] = []

        for item in items:
            item_id = str(item.get("id", "item"))
            path = str(item.get("path", "")).strip()

            if not path:
                results.append(
                    {
                        "id": item_id,
                        "ok": False,
                        "error": "missing_path",
                    }
                )
                continue

            try:
                payload = self._embed_single(path)
                payload["id"] = item_id
                results.append(payload)
            except Exception as exc:  # noqa: BLE001
                results.append(
                    {
                        "id": item_id,
                        "ok": False,
                        "error": str(exc),
                    }
                )

        return {
            "ok": True,
            "backend": "transformers",
            "service": "clip-image-embedding",
            "model": self.model_name,
            "embedding_version": self.embedding_version,
            "device": self.device,
            "results": results,
        }

    def _embed_single(self, path: str) -> dict[str, Any]:
        source = Image.open(path)
        source = ImageOps.exif_transpose(source)
        source = source.convert("RGBA")

        trimmed = self._trim_background(source)
        rgb = self._flatten_alpha(trimmed)
        prepared = self._prepare_crops(rgb)

        crop_tensors = [item.image for item in prepared]
        inputs = self.processor(images=crop_tensors, return_tensors="pt")
        inputs = {key: value.to(self.device) for key, value in inputs.items()}

        with torch.no_grad():
            outputs = self.model.vision_model(pixel_values=inputs["pixel_values"])
            features = self.model.visual_projection(outputs.pooler_output)
            features = torch.nn.functional.normalize(features, dim=-1)

        crop_embeddings: dict[str, list[float]] = {}

        for index, crop in enumerate(prepared):
            crop_embeddings[crop.crop_name] = self._tensor_to_vector(features[index])

        full_vector = crop_embeddings["full"]
        metadata = {
            "width": rgb.width,
            "height": rgb.height,
            "blur_score": round(self._blur_score(rgb), 6),
        }

        return {
            "ok": True,
            "embedding": full_vector,
            "crop_embeddings": crop_embeddings,
            "shoe_probability": round(self._shoe_probability(full_vector), 6),
            "metadata": metadata,
        }

    def _build_text_features(self) -> dict[str, torch.Tensor]:
        prompts = POSITIVE_PROMPTS + NEGATIVE_PROMPTS
        inputs = self.processor(text=prompts, return_tensors="pt", padding=True, truncation=True)
        inputs = {key: value.to(self.device) for key, value in inputs.items()}

        with torch.no_grad():
            outputs = self.model.text_model(
                input_ids=inputs["input_ids"],
                attention_mask=inputs["attention_mask"],
            )
            features = self.model.text_projection(outputs.pooler_output)
            features = torch.nn.functional.normalize(features, dim=-1)

        positive_count = len(POSITIVE_PROMPTS)

        return {
            "positive": features[:positive_count],
            "negative": features[positive_count:],
        }

    def _shoe_probability(self, embedding: list[float]) -> float:
        vector = torch.tensor(embedding, dtype=torch.float32, device=self.device)
        positive = torch.matmul(self._text_features["positive"], vector).mean()
        negative = torch.matmul(self._text_features["negative"], vector).mean()
        margin = float((positive - negative).cpu().item())

        return 1.0 / (1.0 + math.exp(-margin * 4))

    def _flatten_alpha(self, image: Image.Image) -> Image.Image:
        if image.mode != "RGBA":
            return image.convert("RGB")

        background = Image.new("RGBA", image.size, (255, 255, 255, 255))
        merged = Image.alpha_composite(background, image)

        return merged.convert("RGB")

    def _trim_background(self, image: Image.Image) -> Image.Image:
        bounds = self._foreground_bounds(image)

        if bounds is None:
            return image

        x0, y0, x1, y1 = bounds
        width = x1 - x0
        height = y1 - y0

        if width < image.width * 0.08 or height < image.height * 0.08:
            return image

        margin_x = max(4, int(width * 0.08))
        margin_y = max(4, int(height * 0.08))
        crop_box = (
            max(0, x0 - margin_x),
            max(0, y0 - margin_y),
            min(image.width, x1 + margin_x),
            min(image.height, y1 + margin_y),
        )

        return image.crop(crop_box)

    def _foreground_bounds(self, image: Image.Image) -> tuple[int, int, int, int] | None:
        rgba = np.asarray(image.convert("RGBA"), dtype=np.uint8)
        rgb = rgba[:, :, :3].astype(np.float32)
        alpha = rgba[:, :, 3]
        background_rgb, tolerance = self._background_reference(rgba)

        distance_from_background = np.sqrt(np.sum((rgb - background_rgb) ** 2, axis=2))

        has_transparency = bool(np.any(alpha < 245))
        if has_transparency:
            mask = np.logical_and(alpha > 10, np.logical_or(distance_from_background > (tolerance * 0.65), alpha < 245))
        else:
            mask = distance_from_background > tolerance

        coordinates = np.argwhere(mask)
        if coordinates.size == 0:
            return None

        y0, x0 = coordinates.min(axis=0)
        y1, x1 = coordinates.max(axis=0) + 1

        return int(x0), int(y0), int(x1), int(y1)

    def _background_reference(self, rgba: np.ndarray) -> tuple[np.ndarray, float]:
        top = rgba[0, :, :]
        bottom = rgba[-1, :, :]
        left = rgba[:, 0, :]
        right = rgba[:, -1, :]
        border = np.concatenate([top, bottom, left, right], axis=0)

        opaque_border = border[border[:, 3] > 10]
        samples = opaque_border if opaque_border.size > 0 else border
        colors = samples[:, :3].astype(np.float32)
        background_rgb = np.median(colors, axis=0)
        spread = np.median(np.sqrt(np.sum((colors - background_rgb) ** 2, axis=1)))
        tolerance = float(np.clip(18.0 + (spread * 2.2), 30.0, 60.0))

        return background_rgb, tolerance

    def _prepare_crops(self, image: Image.Image) -> list[PreparedImage]:
        crops = [
            PreparedImage("full", self._pad_to_square(image)),
            PreparedImage("center", self._center_crop(image)),
            PreparedImage("focus", self._focus_crop(image)),
            PreparedImage("lower_center", self._lower_center_crop(image)),
            PreparedImage("tilt_left", self._tilt_crop(image, -12)),
            PreparedImage("tilt_right", self._tilt_crop(image, 12)),
        ]

        return [
            PreparedImage(crop.crop_name, crop.image.resize((self.image_size, self.image_size), Image.Resampling.BICUBIC))
            for crop in crops
        ]

    def _pad_to_square(self, image: Image.Image) -> Image.Image:
        side = max(image.width, image.height)
        canvas = Image.new("RGB", (side, side), (255, 255, 255))
        offset = ((side - image.width) // 2, (side - image.height) // 2)
        canvas.paste(image, offset)

        return canvas

    def _center_crop(self, image: Image.Image) -> Image.Image:
        side = max(32, int(min(image.width, image.height) * 0.88))

        return ImageOps.fit(image, (side, side), method=Image.Resampling.BICUBIC, centering=(0.5, 0.5))

    def _focus_crop(self, image: Image.Image) -> Image.Image:
        rgb = np.asarray(image.convert("RGB"), dtype=np.uint8)
        distance_from_white = np.sqrt(np.sum((255 - rgb.astype(np.float32)) ** 2, axis=2))
        mask = distance_from_white > 20
        coordinates = np.argwhere(mask)

        if coordinates.size == 0:
            return self._lower_center_crop(image)

        y0, x0 = coordinates.min(axis=0)
        y1, x1 = coordinates.max(axis=0) + 1
        width = x1 - x0
        height = y1 - y0

        if width < image.width * 0.12 or height < image.height * 0.12:
            return self._lower_center_crop(image)

        side = max(width, height)
        side = min(max(side, int(min(image.width, image.height) * 0.7)), max(image.width, image.height))
        cx = (x0 + x1) // 2
        cy = (y0 + y1) // 2
        half = side // 2
        left = max(0, cx - half)
        top = max(0, cy - half)
        right = min(image.width, left + side)
        bottom = min(image.height, top + side)
        left = max(0, right - side)
        top = max(0, bottom - side)

        return image.crop((left, top, right, bottom))

    def _lower_center_crop(self, image: Image.Image) -> Image.Image:
        side = min(image.width, image.height)
        left = max(0, (image.width - side) // 2)
        top = max(0, int((image.height - side) * 0.35))
        top = min(top, max(0, image.height - side))

        return image.crop((left, top, left + side, top + side))

    def _tilt_crop(self, image: Image.Image, degrees: float) -> Image.Image:
        rotated = image.rotate(
            degrees,
            resample=Image.Resampling.BICUBIC,
            expand=True,
            fillcolor=(255, 255, 255),
        )

        return self._pad_to_square(rotated)

    def _blur_score(self, image: Image.Image) -> float:
        gray = np.asarray(image.convert("L"), dtype=np.float32) / 255.0
        dx = np.diff(gray, axis=1)
        dy = np.diff(gray, axis=0)

        return float(np.var(dx) + np.var(dy))

    def _tensor_to_vector(self, tensor: torch.Tensor) -> list[float]:
        return [round(float(value), 8) for value in tensor.detach().cpu().tolist()]


def read_batch_items(input_path: str) -> list[dict[str, Any]]:
    with open(input_path, "r", encoding="utf-8") as handle:
        payload = json.load(handle)

    if isinstance(payload, dict) and isinstance(payload.get("items"), list):
        return [item for item in payload["items"] if isinstance(item, dict)]

    if isinstance(payload, list):
        return [item for item in payload if isinstance(item, dict)]

    raise RuntimeError("Invalid batch input payload.")


def emit(payload: dict[str, Any], code: int = 0) -> int:
    json.dump(payload, sys.stdout, separators=(",", ":"))
    sys.stdout.write("\n")
    sys.stdout.flush()

    return code


def main() -> int:
    parser = argparse.ArgumentParser(description="Local visual search embedding service")
    parser.add_argument("--model", default=os.environ.get("VISUAL_SEARCH_EMBEDDING_MODEL", "openai/clip-vit-base-patch32"))
    parser.add_argument("--embedding-version", default=os.environ.get("VISUAL_SEARCH_EMBEDDING_VERSION", "clip-b32-v1"))
    subparsers = parser.add_subparsers(dest="command", required=True)

    subparsers.add_parser("health")

    embed_parser = subparsers.add_parser("embed")
    embed_parser.add_argument("--path", required=True)

    batch_parser = subparsers.add_parser("embed-batch")
    batch_parser.add_argument("--input", required=True)

    args = parser.parse_args()

    try:
        service = ClipEmbeddingService(args.model, args.embedding_version)
    except Exception as exc:  # noqa: BLE001
        return emit(
            {
                "ok": False,
                "error": str(exc),
                "model": args.model,
                "embedding_version": args.embedding_version,
            },
            code=1,
        )

    try:
        if args.command == "health":
            return emit(service.health())

        if args.command == "embed":
            return emit(
                {
                    **service.health(),
                    "result": service.embed_path(args.path),
                }
            )

        if args.command == "embed-batch":
            items = read_batch_items(args.input)
            return emit(service.embed_batch(items))

        return emit({"ok": False, "error": "unsupported_command"}, code=1)
    except Exception as exc:  # noqa: BLE001
        return emit(
            {
                "ok": False,
                "error": str(exc),
                "model": args.model,
                "embedding_version": args.embedding_version,
            },
            code=1,
        )


if __name__ == "__main__":
    raise SystemExit(main())
