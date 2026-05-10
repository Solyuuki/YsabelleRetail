## Assistant Image Flow Note

Current storefront assistant image-search architecture after the composer separation fixes:

1. `resources/views/components/storefront/chat-widget.blade.php`
   renders:
   - a dedicated attach button
   - a separate refine button
   - a compact composer image chip
   - a hidden file input
   - the refine drawer and retry controls
2. `resources/js/storefront/modules/chat.js`
   now keeps the composer actions independent:
   - `handleAttachImage()` only opens the file picker
   - `handleImageSelected()` only validates and stores the selected file, then updates the preview/chip
   - `handleOpenRefinePanel()` only opens the refine drawer
   - `handleSend()` submits either the attached image search or a text message
   - `handleRetryImage()` retries only a failed image request
3. `routes/storefront.php`
   maps `POST /assistant/visual-search` to `App\Http\Controllers\Storefront\StorefrontVisualSearchController`.
4. `App\Http\Requests\Storefront\Assistant\StorefrontVisualSearchRequest`
   validates:
   - required uploaded image
   - maximum size of 10 MB
   - supported MIME / extension combinations
   - image decodability for formats that can be probed server-side
5. `App\Http\Controllers\Storefront\StorefrontVisualSearchController`
   forwards the uploaded file and refine hints to `App\Services\Storefront\VisualProductSearchService`.
6. `App\Services\Storefront\VisualProductSearchService`
   orchestrates the full search pipeline:
   - upload read through `VisualSearchImageSource`
   - fallback feature extraction through `ImageFeatureExtractor`
   - embedding generation through `VisualSearchEmbeddingService`
   - indexed catalog lookup through `VisualSearchIndexService`
   - candidate ranking and confidence-aware response shaping
7. `App\Services\Storefront\VisualSearchEmbeddingService`
   is a real ML path, not a fake placeholder:
   - runs a local Python CLI
   - uses the configured CLIP-family model (`openai/clip-vit-base-patch32` by default)
   - generates embeddings for uploads and catalog images
8. `App\Services\Storefront\VisualSearchIndexService`
   stores:
   - catalog image embeddings
   - crop embeddings
   - fallback GD features
   - image metadata needed for health checks and rebuilding
9. `App\Services\Storefront\ImageFeatureExtractor`
   is the non-ML fallback path:
   - perceptual hash
   - color histogram
   - shape profiles
   - dominant colors
   - mean RGB
   - edge density
   - foreground ratio
10. `VisualProductSearchService`
    now exposes the distinction clearly in responses:
    - embedding-backed matches can return normal visual-match wording
    - fallback-only matches are labeled as image-cue/catalog matches
    - failed processing stays failed and does not masquerade as a successful visual result

What the current visual search is and is not:

- It is a real ML / embedding-based visual search path when the embedding service is available.
- It uses image embeddings plus cosine similarity against indexed catalog images.
- It also has a heuristic fallback path based on GD image features and product-level scoring.
- It is not OpenAI Vision.
- It is not metadata-only matching.
- It is not color-only matching.
- It is not generic fallback only.

Frontend behavior after the separation fix:

- Attaching an image does not open the refine drawer.
- Attaching an image does not auto-run visual search.
- The selected image stays attached in the composer until the user removes it.
- The refine drawer opens only from refine controls.
- Failed image requests keep the attached image and expose compact retry state.
- Session / CSRF failures no longer need the refine drawer to surface retry.
- The chat thread no longer emits duplicate "photo attached" bubbles for image selection.
