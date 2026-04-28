<?php

namespace App\Services\Storefront;

use GdImage;

class ImageFeatureExtractor
{
    public const FEATURE_VERSION = 'v1';

    public function extractFromBinary(string $binary): ?array
    {
        if ($binary === '') {
            return null;
        }

        $image = @imagecreatefromstring($binary);

        if (! $image instanceof GdImage) {
            return null;
        }

        $source = $this->flattenTransparency($image);
        imagedestroy($image);

        $width = imagesx($source);
        $height = imagesy($source);

        if ($width < 2 || $height < 2) {
            imagedestroy($source);

            return null;
        }

        $featureImage = $this->resize($source, 32, 32);
        $hashImage = $this->resize($source, 17, 16);
        $shapeImage = $this->resize($source, 16, 16);

        $features = [
            'feature_version' => self::FEATURE_VERSION,
            'width' => $width,
            'height' => $height,
            'aspect_ratio' => round($width / max($height, 1), 6),
            'perceptual_hash' => $this->differenceHash($hashImage),
            'color_histogram' => $this->colorHistogram($featureImage),
            'shape_profile_x' => $this->shapeProfileX($shapeImage),
            'shape_profile_y' => $this->shapeProfileY($shapeImage),
            'dominant_colors' => $this->dominantColors($featureImage),
            'mean_red' => 0.0,
            'mean_green' => 0.0,
            'mean_blue' => 0.0,
            'edge_density' => 0.0,
            'foreground_ratio' => 0.0,
        ];

        [$meanRed, $meanGreen, $meanBlue, $foregroundRatio, $edgeDensity] = $this->imageMoments($featureImage);

        $features['mean_red'] = $meanRed;
        $features['mean_green'] = $meanGreen;
        $features['mean_blue'] = $meanBlue;
        $features['foreground_ratio'] = $foregroundRatio;
        $features['edge_density'] = $edgeDensity;

        imagedestroy($source);
        imagedestroy($featureImage);
        imagedestroy($hashImage);
        imagedestroy($shapeImage);

        return $features;
    }

    private function flattenTransparency(GdImage $source): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

        return $canvas;
    }

    private function resize(GdImage $source, int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        return $canvas;
    }

    private function differenceHash(GdImage $image): string
    {
        $bits = '';

        for ($y = 0; $y < 16; $y++) {
            for ($x = 0; $x < 16; $x++) {
                $left = $this->pixelBrightness($image, $x, $y);
                $right = $this->pixelBrightness($image, $x + 1, $y);
                $bits .= $left >= $right ? '1' : '0';
            }
        }

        return $bits;
    }

    private function colorHistogram(GdImage $image): array
    {
        $bins = array_fill(0, 24, 0.0);
        $totalWeight = 0.0;

        for ($y = 0; $y < 32; $y++) {
            for ($x = 0; $x < 32; $x++) {
                [$red, $green, $blue] = $this->pixelRgb($image, $x, $y);
                $foregroundWeight = $this->foregroundWeight($red, $green, $blue);

                if ($foregroundWeight <= 0.0) {
                    continue;
                }

                [$hue, $saturation] = $this->rgbToHueSaturation($red, $green, $blue);
                $hueBin = min(11, (int) floor(($hue / 360) * 12));
                $satBin = $saturation >= 0.45 ? 1 : 0;
                $index = ($satBin * 12) + $hueBin;

                $bins[$index] += $foregroundWeight;
                $totalWeight += $foregroundWeight;
            }
        }

        if ($totalWeight <= 0.0) {
            return $bins;
        }

        return array_map(
            fn (float $value): float => round($value / $totalWeight, 6),
            $bins,
        );
    }

    private function dominantColors(GdImage $image): array
    {
        $buckets = [];

        for ($y = 0; $y < 32; $y++) {
            for ($x = 0; $x < 32; $x++) {
                [$red, $green, $blue] = $this->pixelRgb($image, $x, $y);
                $foregroundWeight = $this->foregroundWeight($red, $green, $blue);

                if ($foregroundWeight <= 0.0) {
                    continue;
                }

                $bucketRed = (int) floor($red / 32) * 32;
                $bucketGreen = (int) floor($green / 32) * 32;
                $bucketBlue = (int) floor($blue / 32) * 32;
                $bucket = sprintf('%02x%02x%02x', $bucketRed, $bucketGreen, $bucketBlue);

                $buckets[$bucket] = ($buckets[$bucket] ?? 0.0) + $foregroundWeight;
            }
        }

        arsort($buckets);

        return array_slice(
            array_map(fn (string $bucket): string => '#'.$bucket, array_keys($buckets)),
            0,
            3,
        );
    }

    private function imageMoments(GdImage $image): array
    {
        $sumRed = 0.0;
        $sumGreen = 0.0;
        $sumBlue = 0.0;
        $weightTotal = 0.0;
        $foregroundPixels = 0.0;
        $edgeTotal = 0.0;

        for ($y = 0; $y < 32; $y++) {
            for ($x = 0; $x < 32; $x++) {
                [$red, $green, $blue] = $this->pixelRgb($image, $x, $y);
                $weight = $this->foregroundWeight($red, $green, $blue);

                if ($weight > 0.0) {
                    $sumRed += ($red / 255) * $weight;
                    $sumGreen += ($green / 255) * $weight;
                    $sumBlue += ($blue / 255) * $weight;
                    $weightTotal += $weight;
                    $foregroundPixels += 1;
                }

                if ($x < 31) {
                    $edgeTotal += abs($this->pixelBrightness($image, $x, $y) - $this->pixelBrightness($image, $x + 1, $y)) / 255;
                }

                if ($y < 31) {
                    $edgeTotal += abs($this->pixelBrightness($image, $x, $y) - $this->pixelBrightness($image, $x, $y + 1)) / 255;
                }
            }
        }

        $totalPixels = 32 * 32;
        $edgePairs = (31 * 32 * 2);

        if ($weightTotal <= 0.0) {
            return [0.0, 0.0, 0.0, 0.0, round($edgeTotal / max($edgePairs, 1), 6)];
        }

        return [
            round($sumRed / $weightTotal, 6),
            round($sumGreen / $weightTotal, 6),
            round($sumBlue / $weightTotal, 6),
            round($foregroundPixels / $totalPixels, 6),
            round($edgeTotal / max($edgePairs, 1), 6),
        ];
    }

    private function shapeProfileX(GdImage $image): array
    {
        $profile = [];

        for ($x = 0; $x < 16; $x++) {
            $foreground = 0.0;

            for ($y = 0; $y < 16; $y++) {
                [$red, $green, $blue] = $this->pixelRgb($image, $x, $y);

                if ($this->foregroundWeight($red, $green, $blue) > 0.0) {
                    $foreground += 1;
                }
            }

            $profile[] = round($foreground / 16, 6);
        }

        return $profile;
    }

    private function shapeProfileY(GdImage $image): array
    {
        $profile = [];

        for ($y = 0; $y < 16; $y++) {
            $foreground = 0.0;

            for ($x = 0; $x < 16; $x++) {
                [$red, $green, $blue] = $this->pixelRgb($image, $x, $y);

                if ($this->foregroundWeight($red, $green, $blue) > 0.0) {
                    $foreground += 1;
                }
            }

            $profile[] = round($foreground / 16, 6);
        }

        return $profile;
    }

    private function pixelRgb(GdImage $image, int $x, int $y): array
    {
        $rgb = imagecolorat($image, $x, $y);

        return [
            ($rgb >> 16) & 0xFF,
            ($rgb >> 8) & 0xFF,
            $rgb & 0xFF,
        ];
    }

    private function pixelBrightness(GdImage $image, int $x, int $y): float
    {
        [$red, $green, $blue] = $this->pixelRgb($image, $x, $y);

        return (0.299 * $red) + (0.587 * $green) + (0.114 * $blue);
    }

    private function foregroundWeight(int $red, int $green, int $blue): float
    {
        $brightness = (0.299 * $red) + (0.587 * $green) + (0.114 * $blue);
        $distance = sqrt(((255 - $red) ** 2) + ((255 - $green) ** 2) + ((255 - $blue) ** 2));

        if ($brightness > 245 && $distance < 30) {
            return 0.0;
        }

        return max(0.15, min(1.0, $distance / 255));
    }

    private function rgbToHueSaturation(int $red, int $green, int $blue): array
    {
        $red /= 255;
        $green /= 255;
        $blue /= 255;

        $max = max($red, $green, $blue);
        $min = min($red, $green, $blue);
        $delta = $max - $min;

        $hue = 0.0;

        if ($delta > 0.0) {
            $hue = match ($max) {
                $red => 60 * fmod((($green - $blue) / $delta), 6),
                $green => 60 * ((($blue - $red) / $delta) + 2),
                default => 60 * ((($red - $green) / $delta) + 4),
            };
        }

        if ($hue < 0) {
            $hue += 360;
        }

        $saturation = $max === 0.0 ? 0.0 : $delta / $max;

        return [$hue, $saturation];
    }
}
