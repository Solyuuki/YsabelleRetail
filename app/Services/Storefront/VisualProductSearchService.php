<?php

namespace App\Services\Storefront;

use App\Models\Catalog\Product;
use App\Models\Storefront\VisualSearchIndexEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VisualProductSearchService
{
    private const USE_CASE_CATEGORY_MAP = [
        'daily' => ['sneakers', 'lifestyle-shoes', 'slip-ons'],
        'running' => ['running'],
        'walking' => ['walking-shoes', 'running'],
        'gym' => ['training-shoes', 'basketball-shoes'],
        'performance' => ['basketball-shoes', 'training-shoes', 'running'],
    ];

    public function __construct(
        private readonly ProductDiscoveryService $productDiscovery,
        private readonly ImageFeatureExtractor $featureExtractor,
        private readonly VisualSearchImageSource $imageSource,
        private readonly VisualSearchIndexService $indexService,
        private readonly VisualSearchEmbeddingService $embeddingService,
    ) {}

    public function search(UploadedFile $image, array $hints = []): array
    {
        $binary = $this->imageSource->loadFromUpload($image);
        $fallbackFeatures = is_string($binary) ? $this->featureExtractor->extractFromBinary($binary) : null;

        try {
            $embeddingPayload = $this->embeddingService->embedUpload($image);
        } catch (\Throwable $exception) {
            $embeddingPayload = null;
            $this->debugLog('embedding_unavailable', [
                'upload_filename' => $image->getClientOriginalName(),
                'message' => $exception->getMessage(),
            ]);
        }

        $embeddingGenerated = is_array($embeddingPayload)
            && ($embeddingPayload['ok'] ?? false) === true
            && is_array($embeddingPayload['embedding'] ?? null);

        $indexEntries = $this->indexService->indexedEntries();
        $indexedEmbeddingEntries = $indexEntries->filter(fn (VisualSearchIndexEntry $entry): bool => is_array($entry->embedding_vector) && $entry->embedding_vector !== []);

        $criteria = $this->productDiscovery->normalizeCriteria([
            'brand_style' => $hints['brand_style'] ?? null,
            'color' => $hints['color'] ?? null,
            'category' => $hints['category'] ?? null,
            'use_case' => $hints['use_case'] ?? null,
        ]);

        $context = [
            'upload_filename' => $image->getClientOriginalName(),
            'embedding_generated' => $embeddingGenerated,
            'index_count' => $indexEntries->count(),
            'indexed_embedding_count' => $indexedEmbeddingEntries->count(),
            'upload_shoe_probability' => round((float) ($embeddingPayload['shoe_probability'] ?? 0.0), 6),
            'upload_blur_score' => round((float) data_get($embeddingPayload, 'metadata.blur_score', 0.0), 6),
        ];

        if ($indexEntries->isEmpty()) {
            $this->debugLog('no_index', $context);

            return $this->safeUnavailableResponse();
        }

        $engine = 'fallback';
        $scoredProducts = collect();

        if ($embeddingGenerated && $indexedEmbeddingEntries->isNotEmpty()) {
            $engine = 'embedding';
            $scoredProducts = $this->rankProductsByEmbedding($embeddingPayload, $indexedEmbeddingEntries, $criteria);
        }

        if ($scoredProducts->isEmpty() && is_array($fallbackFeatures)) {
            $scoredProducts = $this->rankProductsByFallback($fallbackFeatures, $indexEntries, $criteria);
        }

        if ($scoredProducts->isEmpty()) {
            $reason = $this->noMatchReason($embeddingPayload, $fallbackFeatures, null);
            $this->debugLog('no_match', $context + [
                'reason' => $reason,
                'top_products' => [],
            ]);

            return $this->noMatchResponse($reason);
        }

        $topCandidate = $scoredProducts->first();
        $reason = $this->noMatchReason($embeddingPayload, $fallbackFeatures, $topCandidate);
        $topProducts = $this->debugCandidates($scoredProducts);

        if ($this->shouldRejectAsNonShoe($embeddingPayload, $fallbackFeatures, $topCandidate)) {
            $this->debugLog('non_shoe_rejected', $context + [
                'top_similarity' => $topCandidate['visual_score'] ?? 0.0,
                'top_products' => $topProducts,
            ]);

            return $this->noMatchResponse('non_shoe');
        }

        if (($topCandidate['visual_score'] ?? 0.0) < $this->minCandidateThreshold()) {
            $this->debugLog('no_match', $context + [
                'reason' => $reason,
                'top_products' => $topProducts,
            ]);

            return $this->noMatchResponse($reason);
        }

        $products = $this->presentableCandidates($scoredProducts)
            ->take(4)
            ->map(function (array $candidate): array {
                $product = $this->productDiscovery->formatProduct($candidate['product']);
                $product['match'] = [
                    'confidence' => $candidate['confidence'],
                    'label' => $this->confidenceLabel($candidate['confidence']),
                    'score' => round($candidate['visual_score'], 4),
                    'score_percent' => (int) round($candidate['visual_score'] * 100),
                ];

                return $product;
            })
            ->values()
            ->all();

        $this->debugLog('match', $context + [
            'engine' => $engine,
            'top_products' => $topProducts,
        ]);

        return [
            'answer' => $this->answerFor($topCandidate['confidence'], $topCandidate['product']),
            'match' => [
                'confidence' => $topCandidate['confidence'],
                'label' => $this->confidenceLabel($topCandidate['confidence']),
                'score' => round((float) $topCandidate['visual_score'], 4),
                'score_percent' => (int) round($topCandidate['visual_score'] * 100),
                'engine' => $engine,
                'reason' => $topCandidate['confidence'],
            ],
            'products' => $products,
            'actions' => [
                ['label' => 'Browse full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                ['label' => 'Ask assistant', 'type' => 'message', 'message' => 'Help me choose a shoe for daily use'],
            ],
        ];
    }

    private function rankProductsByEmbedding(array $uploadEmbedding, Collection $indexEntries, array $criteria): Collection
    {
        return $indexEntries
            ->map(function (VisualSearchIndexEntry $entry) use ($uploadEmbedding, $criteria): array {
                $visualScore = $this->embeddingSimilarity($uploadEmbedding, $entry);
                $hintBoost = $visualScore >= $this->minCandidateThreshold()
                    ? $this->hintBoost($entry->product, $criteria)
                    : 0.0;
                $availabilityBoost = $visualScore >= $this->minCandidateThreshold()
                    ? $this->availabilityBoost($entry->product)
                    : 0.0;
                $merchandisingBoost = $visualScore >= $this->minCandidateThreshold()
                    ? $this->merchandisingBoost($entry->product)
                    : 0.0;
                $finalScore = min(1.0, $visualScore + $hintBoost + $availabilityBoost + $merchandisingBoost);

                return [
                    'product' => $entry->product,
                    'entry' => $entry,
                    'visual_score' => round($visualScore, 6),
                    'score' => round($finalScore, 6),
                    'hint_boost' => round($hintBoost, 6),
                    'availability_boost' => round($availabilityBoost, 6),
                    'merchandising_boost' => round($merchandisingBoost, 6),
                    'image_identity' => $this->imageIdentity($entry),
                    'entry_role_boost' => $this->entryRoleBoost($entry),
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['product'] instanceof Product)
            ->pipe(fn (Collection $candidates): Collection => $this->finalizeRankedCandidates($candidates));
    }

    private function rankProductsByFallback(array $uploadFeatures, Collection $indexEntries, array $criteria): Collection
    {
        return $indexEntries
            ->map(function (VisualSearchIndexEntry $entry) use ($uploadFeatures, $criteria): array {
                $visualScore = $this->fallbackSimilarity($uploadFeatures, $entry);
                $hintBoost = $visualScore >= $this->minCandidateThreshold()
                    ? $this->hintBoost($entry->product, $criteria)
                    : 0.0;
                $availabilityBoost = $visualScore >= $this->minCandidateThreshold()
                    ? $this->availabilityBoost($entry->product)
                    : 0.0;
                $merchandisingBoost = $visualScore >= $this->minCandidateThreshold()
                    ? $this->merchandisingBoost($entry->product)
                    : 0.0;
                $finalScore = min(1.0, $visualScore + $hintBoost + $availabilityBoost + $merchandisingBoost);

                return [
                    'product' => $entry->product,
                    'entry' => $entry,
                    'visual_score' => round($visualScore, 6),
                    'score' => round($finalScore, 6),
                    'hint_boost' => round($hintBoost, 6),
                    'availability_boost' => round($availabilityBoost, 6),
                    'merchandising_boost' => round($merchandisingBoost, 6),
                    'image_identity' => $this->imageIdentity($entry),
                    'entry_role_boost' => $this->entryRoleBoost($entry),
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['product'] instanceof Product)
            ->pipe(fn (Collection $candidates): Collection => $this->finalizeRankedCandidates($candidates));
    }

    private function finalizeRankedCandidates(Collection $candidates): Collection
    {
        if ($candidates->isEmpty()) {
            return collect();
        }

        $clusterSizes = $candidates
            ->countBy(fn (array $candidate): string => (string) ($candidate['image_identity'] ?? 'unknown'));

        return $candidates
            ->map(function (array $candidate) use ($clusterSizes): array {
                $clusterSize = max(1, (int) ($clusterSizes[$candidate['image_identity']] ?? 1));
                $imageUniquenessScore = round(1 / $clusterSize, 6);
                $duplicatePenalty = min(0.03, max(0, $clusterSize - 1) * 0.006);
                $uniquenessBoost = min(0.02, $imageUniquenessScore * 0.02);
                $rankScore = min(1.0, max(0.0, $candidate['score'] + $uniquenessBoost - $duplicatePenalty));
                $clusterSelectScore = round(
                    ($candidate['visual_score'] * 1000)
                    + (($candidate['hint_boost'] ?? 0.0) * 100)
                    + (($candidate['entry_role_boost'] ?? 0.0) * 10)
                    + (($candidate['availability_boost'] ?? 0.0) * 5)
                    + (($candidate['merchandising_boost'] ?? 0.0) * 3)
                    + ($imageUniquenessScore * 2),
                    6,
                );

                return $candidate + [
                    'image_cluster_size' => $clusterSize,
                    'image_uniqueness_score' => $imageUniquenessScore,
                    'score' => round($rankScore, 6),
                    'cluster_select_score' => $clusterSelectScore,
                ];
            })
            ->groupBy(fn (array $candidate): string => (string) $candidate['image_identity'])
            ->map(fn (Collection $group): array => $group->sortByDesc('cluster_select_score')->first())
            ->groupBy(fn (array $candidate): int => $candidate['product']->id)
            ->map(function (Collection $group): array {
                $best = $group->sortByDesc('score')->first();
                $best['confidence'] = $this->confidenceForScore($best['visual_score']);

                return $best;
            })
            ->sortByDesc('score')
            ->values();
    }

    private function embeddingSimilarity(array $uploadEmbedding, VisualSearchIndexEntry $entry): float
    {
        $queryVectors = $this->embeddingVectorsFromPayload($uploadEmbedding);
        $entryVectors = $this->embeddingVectorsFromEntry($entry);

        if ($queryVectors === [] || $entryVectors === []) {
            return 0.0;
        }

        $bestScore = 0.0;

        foreach ($queryVectors as $queryVector) {
            foreach ($entryVectors as $entryVector) {
                $bestScore = max($bestScore, $this->cosineSimilarity($queryVector, $entryVector));
            }
        }

        return $bestScore;
    }

    private function embeddingVectorsFromPayload(array $payload): array
    {
        $vectors = [];

        if (is_array($payload['embedding'] ?? null)) {
            $vectors[] = $payload['embedding'];
        }

        if (is_array($payload['crop_embeddings'] ?? null)) {
            foreach ($payload['crop_embeddings'] as $vector) {
                if (is_array($vector)) {
                    $vectors[] = $vector;
                }
            }
        }

        return $vectors;
    }

    private function embeddingVectorsFromEntry(VisualSearchIndexEntry $entry): array
    {
        $vectors = [];

        if (is_array($entry->embedding_vector)) {
            $vectors[] = $entry->embedding_vector;
        }

        if (is_array($entry->embedding_crops)) {
            foreach ($entry->embedding_crops as $vector) {
                if (is_array($vector)) {
                    $vectors[] = $vector;
                }
            }
        }

        return $vectors;
    }

    private function cosineSimilarity(array $left, array $right): float
    {
        $size = min(count($left), count($right));

        if ($size === 0) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $leftNorm = 0.0;
        $rightNorm = 0.0;

        for ($index = 0; $index < $size; $index++) {
            $leftValue = (float) $left[$index];
            $rightValue = (float) $right[$index];
            $dotProduct += $leftValue * $rightValue;
            $leftNorm += $leftValue ** 2;
            $rightNorm += $rightValue ** 2;
        }

        if ($leftNorm <= 0.0 || $rightNorm <= 0.0) {
            return 0.0;
        }

        return max(0.0, min(1.0, $dotProduct / (sqrt($leftNorm) * sqrt($rightNorm))));
    }

    private function fallbackSimilarity(array $uploadFeatures, VisualSearchIndexEntry $entry): float
    {
        $hashSimilarity = $this->hashSimilarity($uploadFeatures['perceptual_hash'], $entry->perceptual_hash);
        $histogramSimilarity = $this->vectorIntersection($uploadFeatures['color_histogram'], $entry->color_histogram ?? []);
        $shapeXSimilarity = $this->vectorSimilarity($uploadFeatures['shape_profile_x'], $entry->shape_profile_x ?? []);
        $shapeYSimilarity = $this->vectorSimilarity($uploadFeatures['shape_profile_y'], $entry->shape_profile_y ?? []);
        $shapeSimilarity = ($shapeXSimilarity + $shapeYSimilarity) / 2;
        $meanColorSimilarity = $this->meanColorSimilarity($uploadFeatures, $entry);
        $edgeSimilarity = 1 - min(abs($uploadFeatures['edge_density'] - $entry->edge_density), 1.0);
        $foregroundSimilarity = 1 - min(abs($uploadFeatures['foreground_ratio'] - $entry->foreground_ratio), 1.0);
        $aspectSimilarity = 1 - min(abs(log(max($uploadFeatures['aspect_ratio'], 0.01) / max($entry->aspect_ratio, 0.01))) / 2, 1.0);

        return round(
            ($hashSimilarity * 0.33)
            + ($histogramSimilarity * 0.22)
            + ($shapeSimilarity * 0.2)
            + ($meanColorSimilarity * 0.1)
            + ($edgeSimilarity * 0.07)
            + ($foregroundSimilarity * 0.04)
            + ($aspectSimilarity * 0.04),
            6,
        );
    }

    private function hashSimilarity(string $left, string $right): float
    {
        $length = min(strlen($left), strlen($right));

        if ($length === 0) {
            return 0.0;
        }

        $distance = 0;

        for ($index = 0; $index < $length; $index++) {
            if ($left[$index] !== $right[$index]) {
                $distance++;
            }
        }

        return 1 - ($distance / $length);
    }

    private function vectorIntersection(array $left, array $right): float
    {
        $size = min(count($left), count($right));

        if ($size === 0) {
            return 0.0;
        }

        $total = 0.0;

        for ($index = 0; $index < $size; $index++) {
            $total += min((float) $left[$index], (float) $right[$index]);
        }

        return min(1.0, $total);
    }

    private function vectorSimilarity(array $left, array $right): float
    {
        $size = min(count($left), count($right));

        if ($size === 0) {
            return 0.0;
        }

        $distance = 0.0;

        for ($index = 0; $index < $size; $index++) {
            $distance += abs((float) $left[$index] - (float) $right[$index]);
        }

        return 1 - min($distance / $size, 1.0);
    }

    private function meanColorSimilarity(array $uploadFeatures, VisualSearchIndexEntry $entry): float
    {
        $distance = abs($uploadFeatures['mean_red'] - $entry->mean_red)
            + abs($uploadFeatures['mean_green'] - $entry->mean_green)
            + abs($uploadFeatures['mean_blue'] - $entry->mean_blue);

        return 1 - min($distance / 3, 1.0);
    }

    private function hintBoost(Product $product, array $criteria): float
    {
        $boost = 0.0;

        if ($criteria['category'] && $product->category?->slug === $criteria['category']) {
            $boost += 0.04;
        }

        if ($criteria['color'] && $this->productHasColor($product, $criteria['color'])) {
            $boost += 0.03;
        }

        if ($criteria['use_case'] && $this->productMatchesUseCase($product, $criteria['use_case'])) {
            $boost += 0.02;
        }

        if ($criteria['brand_style'] !== '' && $this->productMatchesBrandStyle($product, $criteria['brand_style'])) {
            $boost += 0.03;
        }

        return min($boost, 0.08);
    }

    private function availabilityBoost(Product $product): float
    {
        $availability = $this->productDiscovery->formatProduct($product)['availability']['state'] ?? null;

        return match ($availability) {
            'in_stock' => 0.015,
            'low_stock' => 0.008,
            default => 0.0,
        };
    }

    private function merchandisingBoost(Product $product): float
    {
        return $product->is_featured ? 0.003 : 0.0;
    }

    private function imageIdentity(VisualSearchIndexEntry $entry): string
    {
        return $entry->image_url_hash
            ?: ($entry->image_url !== '' ? hash('sha256', $entry->image_url) : 'entry-'.$entry->id);
    }

    private function entryRoleBoost(VisualSearchIndexEntry $entry): float
    {
        return $entry->image_role === 'primary' ? 0.01 : 0.0;
    }

    private function productHasColor(Product $product, string $color): bool
    {
        return $product->variants->contains(function ($variant) use ($color): bool {
            return Str::lower((string) data_get($variant->option_values, 'color')) === Str::lower($color);
        });
    }

    private function productMatchesUseCase(Product $product, string $useCase): bool
    {
        return in_array($product->category?->slug, self::USE_CASE_CATEGORY_MAP[$useCase] ?? [], true);
    }

    private function productMatchesBrandStyle(Product $product, string $brandStyle): bool
    {
        $haystack = Str::lower(collect([
            $product->name,
            $product->style_code,
            $product->short_description,
            $product->description,
            $product->category?->name,
            $product->category?->slug,
        ])->filter()->implode(' '));

        $tokens = preg_split('/[^a-z0-9]+/i', Str::lower($brandStyle)) ?: [];

        foreach ($tokens as $token) {
            if ($token !== '' && strlen($token) > 1 && str_contains($haystack, $token)) {
                return true;
            }
        }

        return false;
    }

    private function confidenceForScore(float $score): string
    {
        return match (true) {
            $score >= $this->strongMatchThreshold() => 'strong_match',
            $score >= $this->likelyMatchThreshold() => 'likely_match',
            $score >= $this->similarMatchThreshold() => 'similar_match',
            $score >= $this->minCandidateThreshold() => 'approximate_match',
            default => 'no_match',
        };
    }

    private function confidenceLabel(string $confidence): string
    {
        return match ($confidence) {
            'strong_match' => 'Strong visual match',
            'likely_match' => 'Likely visual match',
            'similar_match' => 'Similar product',
            'approximate_match' => 'Closest catalog styles',
            default => 'No strong match',
        };
    }

    private function answerFor(string $confidence, Product $product): string
    {
        return match ($confidence) {
            'strong_match' => "This looks like a strong match for {$product->name}.",
            'likely_match' => "This looks like a likely match for {$product->name}.",
            'similar_match' => 'I did not find an exact match, but these look visually similar.',
            'approximate_match' => 'I did not find a close exact match, but these are the closest catalog styles.',
            default => 'No strong visual match found. Please upload a clearer shoe photo.',
        };
    }

    private function noMatchResponse(string $reason): array
    {
        $answer = match ($reason) {
            'index_unavailable' => 'Visual search is still building its product index. Please try again shortly.',
            'blurred_upload' => 'This photo looks too blurry. Try a clearer shoe photo with the full shoe visible.',
            'non_shoe' => 'I could not clearly detect a shoe in this image. Try a side-view or on-foot shoe photo.',
            'low_similarity', 'no_visual_candidate', 'no_match' => 'I could not find a close catalog match. Try a clearer shoe photo or add color and category hints.',
            default => 'No strong visual match found. Please upload a clearer shoe photo.',
        };

        return [
            'answer' => $answer,
            'match' => [
                'confidence' => 'no_match',
                'label' => $this->confidenceLabel('no_match'),
                'score' => 0.0,
                'score_percent' => 0,
                'reason' => $reason,
            ],
            'products' => [],
            'actions' => [
                ['label' => 'Browse full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                ['label' => 'Try text search', 'type' => 'message', 'message' => 'Show me black running shoes'],
            ],
        ];
    }

    private function safeUnavailableResponse(): array
    {
        return $this->noMatchResponse('index_unavailable');
    }

    private function noMatchReason(?array $embeddingPayload, ?array $fallbackFeatures, ?array $topCandidate): string
    {
        if ($topCandidate === null) {
            if (is_array($embeddingPayload) && (($embeddingPayload['metadata']['blur_score'] ?? 1) < $this->blurFloor())) {
                return 'blurred_upload';
            }

            $shoeProbability = (float) ($embeddingPayload['shoe_probability'] ?? 0.0);

            if ($shoeProbability < $this->shoeProbabilityFloor() && ! $this->resemblesShoe($fallbackFeatures)) {
                return 'non_shoe';
            }

            return 'no_visual_candidate';
        }

        if (($topCandidate['visual_score'] ?? 0.0) < $this->minCandidateThreshold()) {
            return 'low_similarity';
        }

        return 'no_match';
    }

    private function resemblesShoe(?array $features): bool
    {
        $shapeX = $features['shape_profile_x'] ?? [];
        $shapeY = $features['shape_profile_y'] ?? [];

        if (! is_array($shapeX) || ! is_array($shapeY) || $shapeX === [] || $shapeY === []) {
            return false;
        }

        $foregroundWidth = count(array_filter($shapeX, fn (float $value): bool => $value > 0.18));
        $foregroundHeight = count(array_filter($shapeY, fn (float $value): bool => $value > 0.18));
        $foregroundAspect = $foregroundWidth / max($foregroundHeight, 1);
        $topWeight = array_sum(array_slice($shapeY, 0, intdiv(count($shapeY), 2)));
        $bottomWeight = array_sum(array_slice($shapeY, intdiv(count($shapeY), 2)));

        return $foregroundAspect >= 1.35
            && $bottomWeight > $topWeight
            && ($features['foreground_ratio'] ?? 0.0) >= 0.1;
    }

    private function shouldRejectAsNonShoe(?array $embeddingPayload, ?array $fallbackFeatures, array $topCandidate): bool
    {
        $visualScore = (float) ($topCandidate['visual_score'] ?? 0.0);

        if ($visualScore >= $this->strongMatchThreshold()) {
            return false;
        }

        if ($this->resemblesShoe($fallbackFeatures)) {
            return false;
        }

        $shoeProbability = (float) ($embeddingPayload['shoe_probability'] ?? 0.0);

        return $shoeProbability < $this->shoeProbabilityFloor();
    }

    private function presentableCandidates(Collection $candidates): Collection
    {
        return $candidates
            ->filter(fn (array $candidate): bool => ($candidate['visual_score'] ?? 0.0) >= $this->minCandidateThreshold())
            ->values();
    }

    private function debugCandidates(Collection $candidates, int $limit = 5): array
    {
        return $candidates
            ->take($limit)
            ->map(fn (array $candidate): array => [
                'product_id' => $candidate['product']->id,
                'similarity' => round($candidate['visual_score'], 4),
                'confidence' => $candidate['confidence'],
            ])
            ->values()
            ->all();
    }

    private function debugLog(string $event, array $context): void
    {
        if (! app()->environment('local') || ! config('storefront.assistant.visual_search.debug', false)) {
            return;
        }

        Log::debug('visual-search.'.$event, $context);
    }

    private function strongMatchThreshold(): float
    {
        return (float) config('storefront.assistant.visual_search.thresholds.strong_match', 0.92);
    }

    private function likelyMatchThreshold(): float
    {
        return (float) config('storefront.assistant.visual_search.thresholds.likely_match', 0.86);
    }

    private function similarMatchThreshold(): float
    {
        return (float) config('storefront.assistant.visual_search.thresholds.similar_match', 0.78);
    }

    private function minCandidateThreshold(): float
    {
        return (float) config('storefront.assistant.visual_search.thresholds.min_candidate', 0.72);
    }

    private function shoeProbabilityFloor(): float
    {
        return (float) config('storefront.assistant.visual_search.thresholds.shoe_probability_floor', 0.42);
    }

    private function blurFloor(): float
    {
        return (float) config('storefront.assistant.visual_search.thresholds.blur_floor', 0.0035);
    }
}
