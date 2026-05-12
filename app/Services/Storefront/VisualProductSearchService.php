<?php

namespace App\Services\Storefront;

use App\Models\Catalog\Product;
use App\Services\Catalog\ProductAvailabilityService;
use App\Models\Storefront\VisualSearchIndexEntry;
use App\Support\Storefront\ColorFamilyNormalizer;
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
    private const COLOR_FAMILY_KEYWORDS = [
        'black' => ['black', 'onyx', 'shadow'],
        'white' => ['white'],
        'ivory' => ['ivory', 'cream', 'beige'],
        'blue' => ['blue', 'azure', 'navy'],
        'graphite' => ['graphite', 'grey', 'gray', 'charcoal', 'slate'],
        'gold' => ['gold', 'amber', 'bronze', 'tan'],
        'volt' => ['volt', 'lime', 'neon', 'green'],
    ];

    public function __construct(
        private readonly ProductDiscoveryService $productDiscovery,
        private readonly ProductAvailabilityService $availability,
        private readonly ImageFeatureExtractor $featureExtractor,
        private readonly VisualSearchImageSource $imageSource,
        private readonly VisualSearchIndexService $indexService,
        private readonly VisualSearchEmbeddingService $embeddingService,
        private readonly ColorFamilyNormalizer $colorFamilyNormalizer,
    ) {}

    public function search(UploadedFile $image, array $hints = []): array
    {
        $criteria = $this->productDiscovery->normalizeCriteria([
            'brand_style' => $hints['brand_style'] ?? null,
            'color' => $hints['color'] ?? null,
            'category' => $hints['category'] ?? null,
            'use_case' => $hints['use_case'] ?? null,
            'filename' => pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME),
        ]);
        $materializedUpload = null;
        $uploadContext = $this->uploadContext($image);

        if (! $image->isValid()) {
            $this->logFailure('upload_invalid', $image, [
                'criteria' => $criteria,
                'upload' => $uploadContext,
            ]);

            return $this->failedVisualResponse(
                reason: 'upload_invalid',
                answer: 'I couldn\'t read that upload. Try another photo.',
                engine: 'upload',
            );
        }

        $materializedUpload = $this->imageSource->materializeFromUpload($image);
        $uploadContext = $this->uploadContext($image, $materializedUpload);

        if (! is_array($materializedUpload) || ! is_string($materializedUpload['path'] ?? null) || $materializedUpload['path'] === '') {
            $this->logFailure('upload_materialization_failed', $image, [
                'criteria' => $criteria,
                'upload' => $uploadContext,
            ]);

            return $this->failedVisualResponse(
                reason: 'upload_read_failed',
                answer: 'I couldn\'t read that upload. Try another photo.',
                engine: 'upload',
            );
        }

        try {
            $binary = @file_get_contents($materializedUpload['path']);

            if (! is_string($binary) || $binary === '') {
                $this->logFailure('upload_read_failed', $image, [
                    'criteria' => $criteria,
                    'upload' => $uploadContext,
                ]);

                return $this->failedVisualResponse(
                    reason: 'upload_read_failed',
                    answer: 'I couldn\'t read that upload. Try another photo.',
                    engine: 'upload',
                );
            }

            $fallbackFeatures = $this->featureExtractor->extractFromBinary($binary);
            $embeddingException = null;

            try {
                $embeddingPayload = $this->embeddingService->embedPath($materializedUpload['path']);
            } catch (\Throwable $exception) {
                $embeddingPayload = null;
                $embeddingException = $exception;
                $this->logFailure('embedding_unavailable', $image, [
                    'criteria' => $criteria,
                    'upload' => $uploadContext,
                    'message' => $exception->getMessage(),
                    'exception_class' => $exception::class,
                ]);
                $this->debugLog('embedding_unavailable', [
                    'upload_filename' => $image->getClientOriginalName(),
                    'upload' => $uploadContext,
                    'message' => $exception->getMessage(),
                    'exception_class' => $exception::class,
                ]);
            }

            $embeddingGenerated = is_array($embeddingPayload)
                && ($embeddingPayload['ok'] ?? false) === true
                && is_array($embeddingPayload['embedding'] ?? null);

            if ($embeddingPayload === null && $embeddingException === null && $this->embeddingService->enabled()) {
                $this->logFailure('embedding_missing', $image, [
                    'criteria' => $criteria,
                    'upload' => $uploadContext,
                    'message' => 'Embedding service returned no payload for the upload.',
                ]);
            }

            if (! is_array($fallbackFeatures) && ! $embeddingGenerated) {
                $processingFailureReason = $embeddingException !== null
                    ? 'runtime_unavailable'
                    : ($this->isScreenshotLikeUpload($criteria, $uploadContext, $embeddingPayload, $fallbackFeatures)
                        ? 'screenshot_needs_crop'
                        : 'processing_error');

                $this->logFailure('processing_error', $image, [
                    'criteria' => $criteria,
                    'upload' => $uploadContext,
                    'message' => 'Image processing failed and no embedding was generated.',
                ]);

                return $this->withDebugMeta($this->failedVisualResponse(
                    reason: $processingFailureReason,
                    answer: $this->failedAnswerFor($processingFailureReason),
                    engine: 'processing',
                ));
            }

            $indexEntries = $this->indexService->indexedEntries();
            $indexedEmbeddingEntries = $indexEntries->filter(fn (VisualSearchIndexEntry $entry): bool => is_array($entry->embedding_vector) && $entry->embedding_vector !== []);
            $visualSignals = $this->buildVisualSignals($fallbackFeatures, $embeddingPayload);

            $context = [
                'upload_filename' => $image->getClientOriginalName(),
                'upload' => $uploadContext,
                'embedding_generated' => $embeddingGenerated,
                'index_count' => $indexEntries->count(),
                'indexed_embedding_count' => $indexedEmbeddingEntries->count(),
                'upload_shoe_probability' => round((float) ($embeddingPayload['shoe_probability'] ?? 0.0), 6),
                'upload_blur_score' => round((float) data_get($embeddingPayload, 'metadata.blur_score', 0.0), 6),
                'preprocessing' => is_array($embeddingPayload['metadata'] ?? null) ? $embeddingPayload['metadata'] : null,
                'visual_signals' => $visualSignals,
                'similarity_reached' => false,
            ];

            if ($indexEntries->isEmpty()) {
                $indexAvailability = $this->indexService->availabilityStatus();
                $indexReason = (string) ($indexAvailability['status'] ?? 'index_unavailable');
                $this->logFailure($indexReason, $image, [
                    'criteria' => $criteria,
                    'upload' => $uploadContext,
                    'index_status' => $indexReason,
                    'rebuild_guidance' => $indexAvailability['rebuild_guidance'] ?? null,
                ] + $context);
                $this->debugLog('no_index', $context);

                return $this->withDebugMeta($this->failedVisualResponse(
                    reason: $indexReason,
                    answer: $this->failedAnswerFor($indexReason),
                    engine: 'catalog_unavailable',
                    signals: $visualSignals,
                ), [
                    'index' => $indexAvailability,
                ]);
            }

            $engine = 'fallback';
            $scoredProducts = collect();

            if ($embeddingGenerated && $indexedEmbeddingEntries->isNotEmpty()) {
                $engine = 'embedding';
                $scoredProducts = $this->rankProductsByEmbedding($embeddingPayload, $indexedEmbeddingEntries, $criteria, $visualSignals);
                $context['similarity_reached'] = true;
            }

            if ($scoredProducts->isEmpty() && is_array($fallbackFeatures)) {
                $scoredProducts = $this->rankProductsByFallback($fallbackFeatures, $indexEntries, $criteria, $visualSignals);
                $engine = $engine === 'embedding' ? $engine : 'fallback';
            }

            if ($scoredProducts->isEmpty()) {
                $reason = $this->noMatchReason($embeddingPayload, $fallbackFeatures, null, $criteria, $uploadContext);
                $this->logFailure($reason, $image, [
                    'criteria' => $criteria,
                    'upload' => $uploadContext,
                    'top_products' => [],
                ] + $context);
                $this->debugLog('no_match', $context + [
                    'reason' => $reason,
                    'top_products' => [],
                ]);

                return $this->withDebugMeta($this->failedVisualResponse(
                    reason: $reason,
                    answer: $this->failedAnswerFor($reason),
                    engine: $engine,
                    signals: $visualSignals,
                ), [
                    'top_candidates' => [],
                ]);
            }

            $filterResolution = $this->applyExplicitFilterGuard($scoredProducts, $criteria);
            $scoredProducts = $filterResolution['candidates'];
            $usedExplicitFilterFallback = $filterResolution['used_fallback'];
            $explicitFilterFallbackAnswer = $filterResolution['fallback_answer'];
            $explicitFilterFallbackReason = $filterResolution['fallback_reason'];

            $topCandidate = $scoredProducts->first();
            $reason = $this->noMatchReason($embeddingPayload, $fallbackFeatures, $topCandidate, $criteria, $uploadContext);
            $topProducts = $this->debugCandidates($scoredProducts);
            $searchConfidence = $this->searchConfidenceForCandidate($topCandidate, $engine);

            if ($this->shouldSurfaceClosestMatches($reason, $topCandidate)) {
                $closestMatchResponse = $this->lowConfidenceResponse(
                    candidates: $scoredProducts,
                    criteria: $criteria,
                    topCandidate: $topCandidate,
                    engine: $engine,
                    signals: $visualSignals,
                    reason: $reason,
                );

                $this->debugLog('closest_match_fallback', $context + [
                    'reason' => $reason,
                    'top_similarity' => round((float) ($topCandidate['confidence_score'] ?? 0.0), 6),
                    'top_products' => $topProducts,
                ]);

                return $this->withDebugMeta($closestMatchResponse, [
                    'top_candidates' => $topProducts,
                ]);
            }

            if ($this->shouldFailVisualSearch($reason, $topCandidate)) {
                $this->logFailure($reason, $image, [
                    'criteria' => $criteria,
                    'upload' => $uploadContext,
                    'top_similarity' => round((float) ($topCandidate['confidence_score'] ?? 0.0), 6),
                    'top_products' => $topProducts,
                ] + $context);
                $this->debugLog('failed_visual_search', $context + [
                    'reason' => $reason,
                    'top_similarity' => round((float) ($topCandidate['confidence_score'] ?? 0.0), 6),
                    'top_products' => $topProducts,
                ]);

                return $this->withDebugMeta($this->failedVisualResponse(
                    reason: $reason,
                    answer: $this->failedAnswerFor($reason),
                    engine: $engine,
                    signals: $visualSignals,
                ), [
                    'top_candidates' => $topProducts,
                ]);
            }

            if ($searchConfidence === 'low_confidence' || $usedExplicitFilterFallback) {
                $fallbackResponse = $this->lowConfidenceResponse(
                    candidates: $scoredProducts,
                    criteria: $criteria,
                    topCandidate: $topCandidate,
                    engine: $engine,
                    signals: $visualSignals,
                    answerOverride: $usedExplicitFilterFallback ? $explicitFilterFallbackAnswer : null,
                    reason: $usedExplicitFilterFallback ? $explicitFilterFallbackReason : 'approximate_match',
                );

                $this->debugLog('low_confidence', $context + [
                    'reason' => $usedExplicitFilterFallback ? $explicitFilterFallbackReason : 'approximate_match',
                    'top_similarity' => round((float) ($topCandidate['confidence_score'] ?? 0.0), 6),
                    'top_products' => $topProducts,
                ]);

                return $this->withDebugMeta($fallbackResponse, [
                    'top_candidates' => $topProducts,
                ]);
            }

            $products = $this->presentableCandidates($scoredProducts)
                ->take(4)
                ->map(function (array $candidate) use ($engine): array {
                    $product = $this->productDiscovery->formatProduct($candidate['product']);
                    $product['match'] = [
                        'confidence' => $candidate['confidence'],
                        'label' => $this->confidenceLabel($candidate['confidence'], $engine),
                        'score' => round((float) ($candidate['confidence_score'] ?? $candidate['visual_score']), 4),
                        'score_percent' => (int) round(((float) ($candidate['confidence_score'] ?? $candidate['visual_score'])) * 100),
                    ];

                    return $product;
                })
                ->values()
                ->all();

            $this->debugLog('match', $context + [
                'engine' => $engine,
                'top_products' => $topProducts,
            ]);

            return $this->withDebugMeta([
                'status' => 'success',
                'search_confidence' => $searchConfidence,
                'answer' => $this->successfulMatchAnswer($searchConfidence, $engine, (string) ($topCandidate['confidence'] ?? 'no_match')),
                'match' => [
                    'confidence' => $topCandidate['confidence'],
                    'label' => $this->confidenceLabel($topCandidate['confidence'], $engine),
                    'score' => round((float) ($topCandidate['confidence_score'] ?? $topCandidate['visual_score']), 4),
                    'score_percent' => (int) round(((float) ($topCandidate['confidence_score'] ?? $topCandidate['visual_score'])) * 100),
                    'engine' => $engine,
                    'reason' => $topCandidate['confidence'],
                    'search_confidence' => $searchConfidence,
                ],
                'visual_search' => [
                    'status' => 'success',
                    'confidence' => $searchConfidence,
                    'engine' => $engine,
                    'reason' => $topCandidate['confidence'],
                    'signals' => $visualSignals,
                ],
                'products' => $products,
                'actions' => [
                    ['label' => 'Browse full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
                    ['label' => 'Ask assistant', 'type' => 'message', 'message' => 'Help me choose a shoe for daily use'],
                ],
            ], [
                'top_candidates' => $topProducts,
            ]);
        } finally {
            if (($materializedUpload['temporary'] ?? false) === true && is_string($materializedUpload['path'] ?? null)) {
                @unlink($materializedUpload['path']);
            }
        }
    }

    private function rankProductsByEmbedding(array $uploadEmbedding, Collection $indexEntries, array $criteria, array $visualSignals): Collection
    {
        return $indexEntries
            ->map(function (VisualSearchIndexEntry $entry) use ($uploadEmbedding, $criteria, $visualSignals): array {
                $visualScore = $this->embeddingSimilarity($uploadEmbedding, $entry);
                $signalAdjustment = $this->visualSignalAdjustment($visualSignals, $entry->product, $entry);
                $confidenceScore = min(1.0, max(0.0, $visualScore + $signalAdjustment));
                $hintBoost = $visualScore >= $this->minCandidateThreshold()
                    ? $this->hintBoost($entry->product, $criteria)
                    : 0.0;
                $availabilityBoost = $visualScore >= $this->minCandidateThreshold()
                    ? $this->availabilityBoost($entry->product)
                    : 0.0;
                $merchandisingBoost = $visualScore >= $this->minCandidateThreshold()
                    ? $this->merchandisingBoost($entry->product)
                    : 0.0;
                $finalScore = min(1.0, max(0.0, $confidenceScore + $hintBoost + $availabilityBoost + $merchandisingBoost));

                return [
                    'product' => $entry->product,
                    'entry' => $entry,
                    'visual_score' => round($visualScore, 6),
                    'confidence_score' => round($confidenceScore, 6),
                    'score' => round($finalScore, 6),
                    'signal_adjustment' => round($signalAdjustment, 6),
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

    private function rankProductsByFallback(array $uploadFeatures, Collection $indexEntries, array $criteria, array $visualSignals): Collection
    {
        return $indexEntries
            ->map(function (VisualSearchIndexEntry $entry) use ($uploadFeatures, $criteria, $visualSignals): array {
                $visualScore = $this->fallbackSimilarity($uploadFeatures, $entry);
                $signalAdjustment = $this->visualSignalAdjustment($visualSignals, $entry->product, $entry);
                $confidenceScore = min(1.0, max(0.0, $visualScore + $signalAdjustment));
                $hintBoost = $visualScore >= $this->minCandidateThreshold()
                    ? $this->hintBoost($entry->product, $criteria)
                    : 0.0;
                $availabilityBoost = $visualScore >= $this->minCandidateThreshold()
                    ? $this->availabilityBoost($entry->product)
                    : 0.0;
                $merchandisingBoost = $visualScore >= $this->minCandidateThreshold()
                    ? $this->merchandisingBoost($entry->product)
                    : 0.0;
                $finalScore = min(1.0, max(0.0, $confidenceScore + $hintBoost + $availabilityBoost + $merchandisingBoost));

                return [
                    'product' => $entry->product,
                    'entry' => $entry,
                    'visual_score' => round($visualScore, 6),
                    'confidence_score' => round($confidenceScore, 6),
                    'score' => round($finalScore, 6),
                    'signal_adjustment' => round($signalAdjustment, 6),
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
                    (($candidate['confidence_score'] ?? $candidate['visual_score']) * 1000)
                    + (($candidate['hint_boost'] ?? 0.0) * 100)
                    + (($candidate['signal_adjustment'] ?? 0.0) * 80)
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
                $best['confidence'] = $this->confidenceForScore((float) ($best['confidence_score'] ?? $best['visual_score']));

                return $best;
            })
            ->filter(fn (array $candidate): bool => $this->availability->isDiscoverable($candidate['product']))
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

    private function buildVisualSignals(?array $features, ?array $embeddingPayload): array
    {
        $dominantColors = is_array($features) ? ($features['dominant_colors'] ?? []) : [];
        $meanRed = is_array($features) ? (float) ($features['mean_red'] ?? 0.0) : 0.0;
        $meanGreen = is_array($features) ? (float) ($features['mean_green'] ?? 0.0) : 0.0;
        $meanBlue = is_array($features) ? (float) ($features['mean_blue'] ?? 0.0) : 0.0;
        $foregroundRatio = is_array($features) ? (float) ($features['foreground_ratio'] ?? 0.0) : 0.0;
        $edgeDensity = is_array($features) ? (float) ($features['edge_density'] ?? 0.0) : 0.0;

        $hasVisualFeatures = is_array($features) && ($dominantColors !== [] || $meanRed !== 0.0 || $meanGreen !== 0.0 || $meanBlue !== 0.0);

        if ($hasVisualFeatures) {
            $dominantHex = (string) ($dominantColors[0] ?? '');
            [$red, $green, $blue] = $this->hexToRgb($dominantHex !== '' ? $dominantHex : $this->rgbToHex($meanRed, $meanGreen, $meanBlue));
            [$hue, $saturation, $lightness] = $this->rgbToHsl($red, $green, $blue);
            $colorFamily = $this->inferVisualColorFamily($hue, $saturation, $lightness);
            $lightProfile = $lightness >= 0.78 ? 'light' : ($lightness <= 0.28 ? 'dark' : 'balanced');
        } else {
            [$hue, $saturation, $lightness] = [0.0, 0.0, 0.5];
            $colorFamily = null;
            $lightProfile = 'balanced';
        }

        $brightness = round(($meanRed + $meanGreen + $meanBlue) / 3, 6);

        return [
            'dominant_colors' => $dominantColors,
            'color_family' => $colorFamily,
            'light_profile' => $lightProfile,
            'brightness' => $brightness,
            'foreground_ratio' => round($foregroundRatio, 6),
            'edge_density' => round($edgeDensity, 6),
            'subject' => $hasVisualFeatures && $this->resemblesShoe($features) ? 'shoe' : 'uncertain',
            'shoe_probability' => round((float) ($embeddingPayload['shoe_probability'] ?? 0.0), 6),
            'blur_score' => round((float) data_get($embeddingPayload, 'metadata.blur_score', 0.0), 6),
        ];
    }

    private function visualSignalAdjustment(array $signals, Product $product, VisualSearchIndexEntry $entry): float
    {
        if ($signals === []) {
            return 0.0;
        }

        $adjustment = 0.0;
        $signalColor = $signals['color_family'] ?? null;
        $productColors = $this->productColorFamilies($product);
        $entryTone = $this->entryTone($entry);
        $lightProfile = $signals['light_profile'] ?? null;

        if (is_string($signalColor) && $signalColor !== '' && $productColors !== []) {
            if (in_array($signalColor, $productColors, true)) {
                $adjustment += 0.06;
            } elseif ($this->isCompatibleNeutralColorPair($signalColor, $productColors)) {
                $adjustment += 0.025;
            } else {
                $adjustment -= $this->mismatchPenaltyForColorFamily($signalColor, $productColors);
            }
        }

        if ($lightProfile === 'light') {
            if (array_intersect($productColors, ['black', 'graphite', 'gold', 'volt']) !== []) {
                $adjustment -= 0.05;
            }

            if ($entryTone === 'dark') {
                $adjustment -= 0.035;
            }
        }

        if ($lightProfile === 'dark') {
            if (array_intersect($productColors, ['white', 'ivory']) !== []) {
                $adjustment -= 0.05;
            }

            if ($entryTone === 'light') {
                $adjustment -= 0.035;
            }
        }

        if ($lightProfile === 'balanced' && $entryTone === 'balanced') {
            $adjustment += 0.012;
        }

        return round(max(-0.18, min(0.08, $adjustment)), 6);
    }

    private function productColorFamilies(Product $product): array
    {
        $values = collect($product->variants)
            ->map(fn ($variant): string => (string) data_get($variant->option_values, 'color'))
            ->push($product->name)
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '');

        return $this->colorFamilyNormalizer->familiesForValues($values->all());
    }

    private function entryTone(VisualSearchIndexEntry $entry): string
    {
        $brightness = (($entry->mean_red ?? 0.0) + ($entry->mean_green ?? 0.0) + ($entry->mean_blue ?? 0.0)) / 3;

        return match (true) {
            $brightness >= 0.78 => 'light',
            $brightness <= 0.28 => 'dark',
            default => 'balanced',
        };
    }

    private function mismatchPenaltyForColorFamily(string $signalColor, array $productColors): float
    {
        if (in_array($signalColor, ['white', 'ivory'], true) && array_intersect($productColors, ['black', 'graphite', 'gold', 'volt']) !== []) {
            return 0.12;
        }

        if (in_array($signalColor, ['black', 'graphite'], true) && array_intersect($productColors, ['white', 'ivory', 'gold']) !== []) {
            return 0.1;
        }

        if ($signalColor === 'blue' && array_intersect($productColors, ['gold', 'volt']) !== []) {
            return 0.09;
        }

        return 0.065;
    }

    private function isCompatibleNeutralColorPair(string $signalColor, array $productColors): bool
    {
        $neutralPairs = [
            'white' => ['ivory'],
            'ivory' => ['white'],
            'black' => ['graphite'],
            'graphite' => ['black'],
        ];

        return array_intersect($neutralPairs[$signalColor] ?? [], $productColors) !== [];
    }

    private function inferVisualColorFamily(float $hue, float $saturation, float $lightness): ?string
    {
        if ($lightness >= 0.86 && $saturation <= 0.12) {
            return 'white';
        }

        if ($lightness >= 0.72 && $saturation <= 0.2) {
            return 'ivory';
        }

        if ($lightness <= 0.22) {
            return 'black';
        }

        if ($lightness <= 0.42 && $saturation <= 0.16) {
            return 'graphite';
        }

        if ($hue >= 185 && $hue <= 250 && $saturation >= 0.2) {
            return 'blue';
        }

        if ($hue >= 34 && $hue <= 58 && $saturation >= 0.28) {
            return 'gold';
        }

        if ($hue >= 62 && $hue <= 105 && $saturation >= 0.35) {
            return 'volt';
        }

        return null;
    }

    private function rgbToHex(float $red, float $green, float $blue): string
    {
        return sprintf(
            '#%02x%02x%02x',
            (int) round(max(0, min(255, $red * 255))),
            (int) round(max(0, min(255, $green * 255))),
            (int) round(max(0, min(255, $blue * 255))),
        );
    }

    private function hexToRgb(string $hex): array
    {
        $normalized = ltrim($hex, '#');

        if (strlen($normalized) !== 6) {
            return [0, 0, 0];
        }

        return [
            hexdec(substr($normalized, 0, 2)),
            hexdec(substr($normalized, 2, 2)),
            hexdec(substr($normalized, 4, 2)),
        ];
    }

    private function rgbToHsl(int $red, int $green, int $blue): array
    {
        $red /= 255;
        $green /= 255;
        $blue /= 255;

        $max = max($red, $green, $blue);
        $min = min($red, $green, $blue);
        $delta = $max - $min;
        $lightness = ($max + $min) / 2;
        $hue = 0.0;
        $saturation = 0.0;

        if ($delta > 0.0) {
            $denominator = 1 - abs((2 * $lightness) - 1);
            $saturation = $denominator > 0.0 ? $delta / $denominator : 0.0;
            $hue = match ($max) {
                $red => 60 * fmod((($green - $blue) / $delta), 6),
                $green => 60 * ((($blue - $red) / $delta) + 2),
                default => 60 * ((($red - $green) / $delta) + 4),
            };
        }

        if ($hue < 0) {
            $hue += 360;
        }

        return [$hue, $saturation, $lightness];
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
        $availability = $this->availability->forProduct($product)['state'] ?? null;

        return match ($availability) {
            ProductAvailabilityService::STATE_IN_STOCK => 0.015,
            ProductAvailabilityService::STATE_LOW_STOCK => 0.008,
            ProductAvailabilityService::STATE_BACKORDER => 0.004,
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
        $expectedFamilies = $this->colorFamilyNormalizer->familiesFromValue($color);

        if ($expectedFamilies === []) {
            $expectedFamilies = [Str::lower(trim($color))];
        }

        return array_intersect($expectedFamilies, $this->productColorFamilies($product)) !== [];
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
            $score >= $this->similarMatchThreshold() => 'approximate_match',
            default => 'no_match',
        };
    }

    private function confidenceLabel(string $confidence, string $engine = 'embedding'): string
    {
        if ($engine === 'fallback') {
            return match ($confidence) {
                'strong_match' => 'Strong image-cue match',
                'likely_match' => 'Likely image-cue match',
                'approximate_match' => 'Approximate image-cue match',
                default => 'No strong image-cue match',
            };
        }

        return match ($confidence) {
            'strong_match' => 'Strong visual match',
            'likely_match' => 'Likely visual match',
            'approximate_match' => 'Similar style',
            default => 'No strong match',
        };
    }

    private function lowConfidenceResponse(
        Collection $candidates,
        array $criteria,
        array $topCandidate,
        string $engine,
        array $signals,
        ?string $answerOverride = null,
        string $reason = 'approximate_match',
    ): array
    {
        $brandStyle = $this->inferBrandStyle($criteria);
        $recommendations = $this->prioritizeFallbackCandidates($candidates, $criteria, $brandStyle)
            ->take(4)
            ->map(function (array $candidate) use ($engine): array {
                $product = $this->productDiscovery->formatProduct($candidate['product']);
                $product['match'] = [
                    'confidence' => 'approximate_match',
                    'label' => $this->confidenceLabel('approximate_match', $engine),
                    'score' => round((float) ($candidate['confidence_score'] ?? $candidate['visual_score'] ?? 0.0), 4),
                    'score_percent' => (int) round(((float) ($candidate['confidence_score'] ?? $candidate['visual_score'] ?? 0.0)) * 100),
                ];

                return $product;
            })
            ->values()
            ->all();

        if ($recommendations === []) {
            return $this->failedVisualResponse(
                reason: 'low_similarity',
                answer: $this->failedAnswerFor('low_similarity'),
                engine: $engine,
                signals: $signals,
            );
        }

        $topScore = (float) data_get($recommendations, '0.match.score', 0.0);

        return [
            'status' => 'success',
            'search_confidence' => 'low_confidence',
            'answer' => $answerOverride ?? $this->lowConfidenceAnswer($reason),
            'match' => [
                'confidence' => 'approximate_match',
                'label' => $this->confidenceLabel('approximate_match', $engine),
                'score' => round($topScore, 4),
                'score_percent' => (int) round($topScore * 100),
                'engine' => $engine,
                'reason' => $reason,
                'search_confidence' => 'low_confidence',
            ],
            'visual_search' => [
                'status' => 'success',
                'confidence' => 'low_confidence',
                'engine' => $engine,
                'reason' => $reason,
                'signals' => $signals,
            ],
            'products' => $recommendations,
            'actions' => $this->matchActions(),
        ];
    }

    private function failedVisualResponse(string $reason, string $answer, string $engine, array $signals = []): array
    {
        return [
            'status' => 'failed',
            'search_confidence' => 'failed',
            'answer' => $answer,
            'match' => [
                'confidence' => 'no_match',
                'label' => $this->confidenceLabel('no_match', $engine === 'fallback' ? 'fallback' : 'embedding'),
                'score' => 0.0,
                'score_percent' => 0,
                'engine' => $engine,
                'reason' => $reason,
                'search_confidence' => 'failed',
            ],
            'visual_search' => [
                'status' => 'failed',
                'confidence' => 'failed',
                'engine' => $engine,
                'reason' => $reason,
                'signals' => $signals,
            ],
            'products' => [],
            'actions' => $this->matchActions(),
        ];
    }

    private function successfulMatchAnswer(string $searchConfidence, string $engine, string $topConfidence): string
    {
        if ($engine === 'fallback') {
            return $this->lowConfidenceAnswer('approximate_match');
        }

        return match ($topConfidence) {
            'strong_match' => 'Found a strong match for this shoe.',
            'likely_match' => 'This looks like a close match.',
            default => match ($searchConfidence) {
                'high_confidence' => 'Found a strong match for this shoe.',
                'medium_confidence' => 'This looks like a close match.',
                default => $this->lowConfidenceAnswer('approximate_match'),
            },
        };
    }

    private function lowConfidenceAnswer(string $reason): string
    {
        return match ($reason) {
            'filter_fallback' => 'No exact match found. Showing closest alternatives.',
            'screenshot_needs_crop' => 'I found a few closest matches, but cropping closer to the shoe should improve accuracy.',
            'blurred_upload' => 'I found a few closest matches, but a clearer photo should improve accuracy.',
            'low_similarity' => 'No exact match found. Showing closest matches from the current catalog.',
            default => 'This looks like a nearby match.',
        };
    }

    private function inferBrandStyle(array $criteria): string
    {
        if (($criteria['brand_style'] ?? '') !== '') {
            return (string) $criteria['brand_style'];
        }

        $ignored = [
            'shoe', 'shoes', 'sneaker', 'sneakers', 'running', 'runner', 'query', 'image', 'photo',
            'screenshot', 'screen', 'download', 'catalog', 'product', 'black', 'white', 'blue', 'gold',
            'daily', 'walking', 'gym', 'performance',
        ];

        return collect($criteria['keywords'] ?? [])
            ->filter(fn (mixed $keyword): bool => is_string($keyword) && strlen($keyword) >= 3)
            ->map(fn (string $keyword): string => Str::lower($keyword))
            ->reject(fn (string $keyword): bool => in_array($keyword, $ignored, true))
            ->take(3)
            ->implode(' ');
    }

    private function prioritizeFallbackCandidates(Collection $candidates, array $criteria, string $brandStyle): Collection
    {
        return $candidates
            ->filter(fn (array $candidate): bool => ($candidate['product'] ?? null) instanceof Product)
            ->map(function (array $candidate) use ($criteria, $brandStyle): array {
                $product = $candidate['product'];
                $brandBoost = $brandStyle !== '' && $this->productMatchesBrandStyle($product, $brandStyle) ? 0.12 : 0.0;
                $categoryBoost = $this->fallbackCategoryBoost($product, $criteria);
                $visualScore = (float) ($candidate['visual_score'] ?? 0.0);
                $fallbackScore = min(1.0, $visualScore + $brandBoost + $categoryBoost + $this->availabilityBoost($product));

                return $candidate + [
                    'fallback_score' => round($fallbackScore, 6),
                    'fallback_brand_boost' => $brandBoost,
                    'fallback_category_boost' => $categoryBoost,
                ];
            })
            ->sortByDesc(fn (array $candidate): array => [
                $candidate['fallback_score'],
                $candidate['visual_score'] ?? 0.0,
                $candidate['score'] ?? 0.0,
            ])
            ->values();
    }

    private function fallbackCategoryBoost(Product $product, array $criteria): float
    {
        $boost = 0.0;

        if (($criteria['category'] ?? null) && $product->category?->slug === $criteria['category']) {
            $boost += 0.08;
        }

        if (($criteria['use_case'] ?? null) && $this->productMatchesUseCase($product, $criteria['use_case'])) {
            $boost += 0.05;
        }

        if (($criteria['color'] ?? null) && $this->productHasColor($product, $criteria['color'])) {
            $boost += 0.03;
        }

        return min($boost, 0.12);
    }

    private function neutralCandidates(Collection $indexEntries): Collection
    {
        return $indexEntries
            ->map(function (VisualSearchIndexEntry $entry): array {
                return [
                    'product' => $entry->product,
                    'visual_score' => 0.0,
                    'score' => round($this->availabilityBoost($entry->product) + $this->merchandisingBoost($entry->product), 6),
                    'confidence' => 'no_match',
                ];
            })
            ->filter(fn (array $candidate): bool => ($candidate['product'] ?? null) instanceof Product)
            ->groupBy(fn (array $candidate): int => $candidate['product']->id)
            ->map(fn (Collection $group): array => $group->first())
            ->values();
    }

    private function noMatchResponse(string $reason): array
    {
        return $this->failedVisualResponse(
            reason: $reason,
            answer: $this->failedAnswerFor($reason),
            engine: 'fallback',
        );
    }

    private function failedAnswerFor(string $reason): string
    {
        return match ($reason) {
            'blurred_upload' => 'I couldn\'t scan that image. Try a clearer photo.',
            'non_shoe' => 'I couldn\'t scan that image. Try another shoe photo.',
            'screenshot_needs_crop' => 'I can read the screenshot, but the shoe is too small/noisy. Try cropping closer.',
            'index_stale' => 'I couldn\'t scan that image right now because visual search is refreshing. Please try again shortly.',
            'index_unavailable' => 'Visual search is unavailable because the current catalog index is empty.',
            'gd_unavailable', 'processing_error', 'runtime_unavailable' => 'Visual search runtime is unavailable right now. Please try again shortly.',
            'upload_invalid', 'upload_materialization_failed', 'upload_read_failed', 'decode_failed', 'empty_image', 'image_too_small' => 'The uploaded image is invalid or unreadable. Try another photo.',
            'low_similarity', 'no_visual_candidate' => 'I couldn\'t find close enough matches in the current catalog for that image.',
            default => 'I couldn\'t scan that image. Try another photo.',
        };
    }

    private function shouldFailVisualSearch(string $reason, array $topCandidate): bool
    {
        if (in_array($reason, ['non_shoe', 'blurred_upload', 'screenshot_needs_crop'], true)) {
            return true;
        }

        return ((float) ($topCandidate['confidence_score'] ?? $topCandidate['visual_score'] ?? 0.0)) < $this->similarMatchThreshold();
    }

    private function isLowConfidenceCandidate(array $topCandidate): bool
    {
        return ($topCandidate['confidence'] ?? null) === 'approximate_match';
    }

    private function searchConfidenceForCandidate(array $topCandidate, string $engine): string
    {
        if ($engine === 'fallback') {
            return 'low_confidence';
        }

        return match ($topCandidate['confidence'] ?? 'no_match') {
            'strong_match' => 'high_confidence',
            'likely_match' => 'medium_confidence',
            default => 'low_confidence',
        };
    }

    private function noMatchReason(
        ?array $embeddingPayload,
        ?array $fallbackFeatures,
        ?array $topCandidate,
        array $criteria = [],
        array $uploadContext = [],
    ): string
    {
        $screenshotLike = $this->isScreenshotLikeUpload($criteria, $uploadContext, $embeddingPayload, $fallbackFeatures);

        if ($topCandidate === null) {
            if (is_array($embeddingPayload) && (($embeddingPayload['metadata']['blur_score'] ?? 1) < $this->blurFloor())) {
                return 'blurred_upload';
            }

            $shoeProbability = (float) ($embeddingPayload['shoe_probability'] ?? 0.0);

            if ($shoeProbability < $this->shoeProbabilityFloor() && ! $this->resemblesShoe($fallbackFeatures)) {
                return $screenshotLike ? 'screenshot_needs_crop' : 'non_shoe';
            }

            return 'no_visual_candidate';
        }

        if ($this->isClearlyNonShoe($embeddingPayload, $fallbackFeatures, $topCandidate)) {
            return $screenshotLike ? 'screenshot_needs_crop' : 'non_shoe';
        }

        if (($topCandidate['confidence_score'] ?? $topCandidate['visual_score'] ?? 0.0) < $this->similarMatchThreshold()) {
            if (is_array($embeddingPayload) && (($embeddingPayload['metadata']['blur_score'] ?? 1) < $this->blurFloor())) {
                return 'blurred_upload';
            }

            if ($screenshotLike) {
                return 'screenshot_needs_crop';
            }

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

    private function clearlyNonShoeShape(?array $features): bool
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
        $balanceGap = abs($bottomWeight - $topWeight);

        return $foregroundAspect < 1.18
            && $balanceGap < 0.55
            && ($features['foreground_ratio'] ?? 0.0) >= 0.08;
    }

    private function shouldFallbackAsRecommendation(?array $embeddingPayload, ?array $fallbackFeatures, array $topCandidate): bool
    {
        if (($topCandidate['confidence_score'] ?? $topCandidate['visual_score'] ?? 0.0) < $this->similarMatchThreshold()) {
            return true;
        }

        return $this->isClearlyNonShoe($embeddingPayload, $fallbackFeatures, $topCandidate);
    }

    private function isClearlyNonShoe(?array $embeddingPayload, ?array $fallbackFeatures, array $topCandidate): bool
    {
        if ($this->resemblesShoe($fallbackFeatures)) {
            return false;
        }

        $shoeProbability = (float) ($embeddingPayload['shoe_probability'] ?? 0.0);
        $visualScore = (float) ($topCandidate['confidence_score'] ?? $topCandidate['visual_score'] ?? 0.0);

        if ($shoeProbability >= $this->shoeProbabilityFloor()) {
            return false;
        }

        if ($this->clearlyNonShoeShape($fallbackFeatures)) {
            return true;
        }

        return $shoeProbability < $this->clearNonShoeFloor()
            && $visualScore < max(0.45, $this->minCandidateThreshold() - 0.08);
    }

    private function presentableCandidates(Collection $candidates): Collection
    {
        return $candidates
            ->filter(fn (array $candidate): bool => ((float) ($candidate['confidence_score'] ?? $candidate['visual_score'] ?? 0.0)) >= $this->similarMatchThreshold())
            ->values();
    }

    private function debugCandidates(Collection $candidates, int $limit = 5): array
    {
        return $candidates
            ->take($limit)
            ->map(function (array $candidate): array {
                $product = $candidate['product'] ?? null;

                if (! $product instanceof Product) {
                    return [
                        'product_id' => null,
                        'similarity' => round((float) ($candidate['match']['score'] ?? $candidate['visual_score'] ?? 0.0), 4),
                        'confidence' => $candidate['match']['confidence'] ?? ($candidate['confidence'] ?? 'no_match'),
                    ];
                }

                return [
                    'product_id' => $product->id,
                    'similarity' => round((float) ($candidate['confidence_score'] ?? $candidate['visual_score'] ?? $candidate['match']['score'] ?? 0.0), 4),
                    'confidence' => $candidate['confidence'] ?? $candidate['match']['confidence'] ?? 'no_match',
                ];
            })
            ->values()
            ->all();
    }

    public function unexpectedFailureResponse(): array
    {
        return $this->failedVisualResponse(
            reason: 'processing_error',
            answer: 'I couldn\'t scan that image right now. Try again shortly.',
            engine: 'error',
        );
    }

    private function logFailure(string $reason, UploadedFile $image, array $context = []): void
    {
        Log::warning('visual-search.failure', $context + [
            'reason' => $reason,
            'upload_filename' => $image->getClientOriginalName(),
            'detected_mime' => $image->getMimeType(),
            'client_mime' => $image->getClientMimeType(),
            'size_bytes' => $image->getSize(),
        ]);
    }

    private function uploadContext(UploadedFile $image, ?array $materializedUpload = null): array
    {
        $realPath = $image->getRealPath();
        $pathname = $image->getPathname();

        return [
            'original_filename' => $image->getClientOriginalName(),
            'client_extension' => Str::lower((string) $image->getClientOriginalExtension()),
            'detected_mime' => $image->getMimeType(),
            'client_mime' => $image->getClientMimeType(),
            'size_bytes' => $image->getSize(),
            'real_path' => is_string($realPath) ? $realPath : null,
            'real_path_exists' => is_string($realPath) && $realPath !== '' ? is_file($realPath) : false,
            'real_path_readable' => is_string($realPath) && $realPath !== '' ? is_readable($realPath) : false,
            'pathname' => is_string($pathname) ? $pathname : null,
            'pathname_exists' => is_string($pathname) && $pathname !== '' ? is_file($pathname) : false,
            'pathname_readable' => is_string($pathname) && $pathname !== '' ? is_readable($pathname) : false,
            'materialized_path' => is_array($materializedUpload) ? ($materializedUpload['path'] ?? null) : null,
            'materialized_temporary' => is_array($materializedUpload) ? (bool) ($materializedUpload['temporary'] ?? false) : null,
            'materialized_disk' => is_array($materializedUpload) ? ($materializedUpload['disk'] ?? null) : null,
        ];
    }

    private function isScreenshotLikeUpload(
        array $criteria,
        array $uploadContext,
        ?array $embeddingPayload,
        ?array $fallbackFeatures,
    ): bool {
        $filename = Str::lower((string) ($criteria['filename'] ?? $uploadContext['original_filename'] ?? ''));

        if ($filename !== '' && preg_match('/screenshot|screen[-_\s]?shot|screen[-_\s]?cap|screen[-_\s]?capture/', $filename) === 1) {
            return true;
        }

        $width = (int) data_get($embeddingPayload, 'metadata.original_width', $fallbackFeatures['width'] ?? 0);
        $height = (int) data_get($embeddingPayload, 'metadata.original_height', $fallbackFeatures['height'] ?? 0);
        $clientMime = Str::lower((string) ($uploadContext['client_mime'] ?? ''));

        return in_array($clientMime, ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'], true)
            && $width >= 900
            && $height >= 600;
    }

    private function debugLog(string $event, array $context): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('storefront.assistant.visual_search.debug', false)) {
            return;
        }

        Log::debug('visual-search.'.$event, $context);
    }

    private function matchActions(): array
    {
        return [
            ['label' => 'Browse full catalog', 'type' => 'link', 'url' => route('storefront.shop')],
            ['label' => 'Ask assistant', 'type' => 'message', 'message' => 'Help me choose a shoe for daily use'],
        ];
    }

    private function applyExplicitFilterGuard(Collection $candidates, array $criteria): array
    {
        $hasExplicitFilters = filled($criteria['color'])
            || filled($criteria['category'])
            || filled($criteria['use_case']);

        if (! $hasExplicitFilters) {
            return [
                'candidates' => $candidates,
                'used_fallback' => false,
                'fallback_answer' => null,
                'fallback_reason' => 'approximate_match',
            ];
        }

        $scored = $candidates
            ->map(function (array $candidate) use ($criteria): array {
                $metrics = $this->explicitFilterMetrics($candidate['product'] ?? null, $criteria);

                return $candidate + $metrics + [
                    'explicit_filter_priority' => (($metrics['explicit_filter_match_count'] ?? 0) * 1000000)
                        + ((float) ($candidate['score'] ?? 0.0) * 1000)
                        + ((float) ($candidate['confidence_score'] ?? $candidate['visual_score'] ?? 0.0) * 100),
                ];
            })
            ->values();

        $exactMatches = $scored
            ->filter(fn (array $candidate): bool => ($candidate['explicit_filter_total'] ?? 0) > 0 && ($candidate['explicit_filter_exact_match'] ?? false) === true)
            ->sortByDesc('score')
            ->values();

        if ($exactMatches->isNotEmpty()) {
            return [
                'candidates' => $exactMatches,
                'used_fallback' => false,
                'fallback_answer' => null,
                'fallback_reason' => 'approximate_match',
            ];
        }

        return [
            'candidates' => $scored->sortByDesc('explicit_filter_priority')->values(),
            'used_fallback' => true,
            'fallback_answer' => $this->explicitFilterFallbackAnswer($criteria),
            'fallback_reason' => 'filter_fallback',
        ];
    }

    private function explicitFilterMetrics(mixed $product, array $criteria): array
    {
        if (! $product instanceof Product) {
            return [
                'explicit_filter_total' => 0,
                'explicit_filter_match_count' => 0,
                'explicit_filter_exact_match' => false,
            ];
        }

        $total = 0;
        $matches = 0;

        if (filled($criteria['color'])) {
            $total++;
            $matches += $this->productHasColor($product, (string) $criteria['color']) ? 1 : 0;
        }

        if (filled($criteria['category'])) {
            $total++;
            $matches += $product->category?->slug === $criteria['category'] ? 1 : 0;
        }

        if (filled($criteria['use_case'])) {
            $total++;
            $matches += $this->productMatchesUseCase($product, (string) $criteria['use_case']) ? 1 : 0;
        }

        return [
            'explicit_filter_total' => $total,
            'explicit_filter_match_count' => $matches,
            'explicit_filter_exact_match' => $total > 0 && $matches === $total,
        ];
    }

    private function explicitFilterFallbackAnswer(array $criteria): string
    {
        $descriptor = $this->explicitFilterDescriptor($criteria);

        return $descriptor !== ''
            ? 'No exact '.$descriptor.' match found. Showing closest alternatives.'
            : 'No exact match found. Showing closest alternatives.';
    }

    private function explicitFilterDescriptor(array $criteria): string
    {
        $parts = [];

        if (filled($criteria['color'])) {
            $parts[] = Str::lower((string) $criteria['color']);
        }

        if (filled($criteria['category'])) {
            $parts[] = $this->humanizeFilterValue((string) $criteria['category']);
        } elseif (filled($criteria['use_case'])) {
            $parts[] = $this->humanizeFilterValue((string) $criteria['use_case']).' shoes';
        }

        return trim(implode(' ', $parts));
    }

    private function humanizeFilterValue(string $value): string
    {
        return Str::of($value)
            ->replace('-', ' ')
            ->lower()
            ->trim()
            ->toString();
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
        return (float) config('storefront.assistant.visual_search.thresholds.shoe_probability_floor', 0.48);
    }

    private function clearNonShoeFloor(): float
    {
        return max(0.18, round($this->shoeProbabilityFloor() * 0.58, 6));
    }

    private function blurFloor(): float
    {
        return (float) config('storefront.assistant.visual_search.thresholds.blur_floor', 0.0015);
    }

    private function shouldSurfaceClosestMatches(string $reason, array $topCandidate): bool
    {
        $score = (float) ($topCandidate['confidence_score'] ?? $topCandidate['visual_score'] ?? 0.0);

        if ($score < $this->minCandidateThreshold()) {
            return false;
        }

        return in_array($reason, ['blurred_upload', 'low_similarity', 'screenshot_needs_crop'], true);
    }

    private function withDebugMeta(array $response, array $debug = []): array
    {
        if (! app()->environment(['local', 'testing']) || ! config('storefront.assistant.visual_search.debug', false)) {
            return $response;
        }

        $response['debug'] = array_filter([
            'runtime' => $this->embeddingService->runtimeDetails(),
            'index' => $this->indexService->availabilityStatus(),
            'top_candidates' => $debug['top_candidates'] ?? null,
        ], fn (mixed $value): bool => $value !== null);

        return $response;
    }
}
