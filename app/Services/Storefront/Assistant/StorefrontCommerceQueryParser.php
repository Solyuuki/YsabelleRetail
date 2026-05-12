<?php

namespace App\Services\Storefront\Assistant;

use Illuminate\Support\Str;

class StorefrontCommerceQueryParser
{
    private const CATEGORY_KEYWORDS = [
        'running' => ['running', 'runner', 'runners', 'jogging', 'jogger', 'tempo'],
        'sneakers' => ['sneaker', 'sneakers', 'casual', 'street', 'rubber shoes'],
        'basketball-shoes' => ['basketball', 'court', 'hoops', 'rebound', 'dunk'],
        'lifestyle-shoes' => ['lifestyle', 'daily', 'everyday', 'fashion', 'city'],
        'training-shoes' => ['sport', 'sports', 'gym', 'training', 'trainer', 'workout', 'active'],
        'walking-shoes' => ['walking', 'walker', 'stroll', 'comfort walk'],
        'slip-ons' => ['slip-on', 'slip ons', 'loafer', 'easy-on'],
        'boots-high-cut' => ['boot', 'boots', 'high-cut', 'rugged', 'hike', 'hiking', 'trail'],
    ];

    private const COLOR_KEYWORDS = [
        'black' => ['black', 'onyx', 'shadow'],
        'white' => ['white'],
        'ivory' => ['ivory', 'cream', 'off white'],
        'blue' => ['blue', 'azure', 'navy'],
        'graphite' => ['graphite', 'grey', 'gray', 'charcoal'],
        'gold' => ['gold', 'metallic'],
        'volt' => ['volt', 'neon', 'lime'],
    ];

    private const LEADING_FILLERS = [
        'can you',
        'can u',
        'could you',
        'please',
        'pls',
        'pre',
        'bro',
        'tol',
        'boss',
        'idol',
        'sir',
        'maam',
        'mam',
        'miss',
        'uy',
    ];

    private const SEARCH_OPENERS = [
        'can you find me',
        'can you find',
        'can u find me',
        'can u find',
        'could you find me',
        'could you find',
        'can you show me',
        'can you show',
        'could you show me',
        'could you show',
        'find me',
        'find this',
        'find',
        'show me',
        'show this',
        'show',
        'search for',
        'look for',
        'looking for',
        'do you have',
        'can i get',
        'have you got',
        'meron bang',
        'meron ba kayo',
        'meron ba',
        'mayroon bang',
        'mayroon ba kayong',
        'mayroon ba',
        'hanap moko',
        'hanap mo ko',
        'hanap ako',
        'hanapan',
        'hanap',
        'pahanap',
    ];

    private const CURRENT_PRODUCT_PHRASES = [
        'this product',
        'this shoe',
        'this pair',
        'this item',
        'find this',
        'show this',
        'the product im viewing',
        'the product i m viewing',
        'the product on this page',
        'ito',
        'nito',
        'neto',
        'nitong item',
        'yung item na to',
        'itong item',
        'itong product',
        'itong shoe',
    ];

    private const AVAILABILITY_KEYWORDS = [
        'availability',
        'available',
        'in stock',
        'low stock',
        'sold out',
        'stock',
        'meron',
        'have',
    ];

    private const QUANTITY_KEYWORDS = [
        'how many',
        'ilan',
        'pairs left',
        'pair left',
        'stocks left',
        'stock left',
    ];

    private const SUPPORT_SIZE_GUIDE_PHRASES = [
        'size guide',
        'sizing guide',
        'true to size',
        'fit guide',
        'size chart',
    ];

    private const PRODUCT_TERMS = [
        'shoe',
        'shoes',
        'sneaker',
        'sneakers',
        'runner',
        'runners',
        'pair',
        'pairs',
        'product',
        'products',
        'collection',
        'catalog',
        'sapatos',
    ];

    private const LOW_SIGNAL_PHRASES = [
        'ano pa options',
        'asdasdasd',
        'bolbol',
        'haha',
        'hahaha',
        'hello',
        'hey',
        'hi',
        'maybe',
        'otp',
        'test',
        'thanks',
        'thank you',
    ];

    public function parse(string $message, array $pageContext = []): array
    {
        $original = trim($message);
        $normalized = $this->normalizeText($original);
        $commerce = $this->normalizeCommerceLanguage($normalized);
        $stripped = $this->stripLeadingFillers($commerce);
        $referencesCurrentProduct = $this->referencesCurrentProduct($stripped);
        $explicitLookup = $this->hasSearchOpener($stripped);
        $availabilityIntent = $this->containsAny($stripped, self::AVAILABILITY_KEYWORDS);
        $quantityIntent = $this->containsAny($stripped, self::QUANTITY_KEYWORDS);
        $supportSizeGuide = $this->containsAny($stripped, self::SUPPORT_SIZE_GUIDE_PHRASES);
        $affordable = $this->containsAny($stripped, ['affordable', 'budget', 'cheap', 'mura']);
        $premium = $this->containsAny($stripped, ['premium', 'expensive', 'mahal']);
        $size = $this->extractSize($stripped);
        [$budgetMin, $budgetMax] = $this->extractBudget($stripped);
        $category = $this->detectCategory($stripped);
        $color = $this->detectColor($stripped);
        $useCase = $this->detectUseCase($stripped);
        $gender = $this->detectGender($stripped);
        $productName = $this->extractProductName(
            original: $original,
            normalized: $stripped,
            explicitLookup: $explicitLookup,
            referencesCurrentProduct: $referencesCurrentProduct,
            category: $category,
            color: $color,
            useCase: $useCase,
            size: $size,
            budgetMin: $budgetMin,
            budgetMax: $budgetMax,
            affordable: $affordable,
            premium: $premium,
            availabilityIntent: $availabilityIntent,
            quantityIntent: $quantityIntent,
        );

        $query = $productName ?: $this->extractSearchQuery(
            normalized: $stripped,
            referencesCurrentProduct: $referencesCurrentProduct,
            category: $category,
            color: $color,
            useCase: $useCase,
            affordable: $affordable,
            premium: $premium,
            budgetMin: $budgetMin,
            budgetMax: $budgetMax,
        );

        $keywords = $this->keywordsFromText($query ?: $stripped);
        $hasLowSignalText = $this->isLowSignalText($stripped);
        $hasProductSignal = $productName !== null
            || $category !== null
            || $color !== null
            || $useCase !== null
            || $size !== null
            || $budgetMin !== null
            || $budgetMax !== null
            || $affordable
            || $premium
            || $referencesCurrentProduct;

        $intent = $this->determineIntent(
            normalized: $stripped,
            hasCurrentProductContext: filled(data_get($pageContext, 'current_product.slug')),
            referencesCurrentProduct: $referencesCurrentProduct,
            hasProductSignal: $hasProductSignal,
            hasLowSignalText: $hasLowSignalText,
            supportSizeGuide: $supportSizeGuide,
            explicitLookup: $explicitLookup,
            productName: $productName,
            size: $size,
            availabilityIntent: $availabilityIntent,
            quantityIntent: $quantityIntent,
            budgetMin: $budgetMin,
            budgetMax: $budgetMax,
            affordable: $affordable,
        );

        return [
            'intent' => $intent,
            'original' => $original,
            'normalized' => $normalized,
            'commerce_normalized' => $stripped,
            'query' => $query,
            'keywords' => $keywords,
            'entities' => array_filter([
                'product_name' => $productName,
                'category' => $category,
                'color' => $color,
                'size' => $size,
                'budget_min' => $budgetMin,
                'budget_max' => $budgetMax,
                'use_case' => $useCase,
                'gender' => $gender,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
            'flags' => [
                'references_current_product' => $referencesCurrentProduct,
                'explicit_lookup' => $explicitLookup,
                'availability_intent' => $availabilityIntent,
                'support_size_guide' => $supportSizeGuide,
                'affordable' => $affordable,
                'premium' => $premium,
                'quantity_intent' => $quantityIntent,
                'has_product_signal' => $hasProductSignal,
                'has_low_signal_text' => $hasLowSignalText,
            ],
        ];
    }

    private function determineIntent(
        string $normalized,
        bool $hasCurrentProductContext,
        bool $referencesCurrentProduct,
        bool $hasProductSignal,
        bool $hasLowSignalText,
        bool $supportSizeGuide,
        bool $explicitLookup,
        ?string $productName,
        ?string $size,
        bool $availabilityIntent,
        bool $quantityIntent,
        ?float $budgetMin,
        ?float $budgetMax,
        bool $affordable,
    ): string {
        if (str_contains($normalized, 'cart') || str_contains($normalized, 'basket') || str_contains($normalized, 'bag')) {
            return 'cart';
        }

        if ($this->containsAny($normalized, ['image search', 'visual search', 'upload', 'photo', 'picture', 'find similar'])) {
            return 'visual_search_guidance';
        }

        if ($supportSizeGuide && ! $referencesCurrentProduct && $productName === null && $size === null) {
            return 'support';
        }

        if ($hasLowSignalText && ! $hasProductSignal && ! $explicitLookup) {
            return 'fallback';
        }

        if ($size !== null && ($referencesCurrentProduct || $hasCurrentProductContext || $productName !== null || $availabilityIntent || $quantityIntent)) {
            return 'size_stock';
        }

        if ($referencesCurrentProduct && $hasCurrentProductContext) {
            return 'current_product';
        }

        if ($productName !== null && ($explicitLookup || $availabilityIntent || $quantityIntent)) {
            return $availabilityIntent ? 'product_availability' : 'product_exact_lookup';
        }

        if (($budgetMin !== null || $budgetMax !== null || $affordable) && $hasProductSignal) {
            return 'budget_search';
        }

        if ($hasProductSignal) {
            return 'product_search';
        }

        return 'fallback';
    }

    private function extractProductName(
        string $original,
        string $normalized,
        bool $explicitLookup,
        bool $referencesCurrentProduct,
        ?string $category,
        ?string $color,
        ?string $useCase,
        ?string $size,
        ?float $budgetMin,
        ?float $budgetMax,
        bool $affordable,
        bool $premium,
        bool $availabilityIntent,
        bool $quantityIntent,
    ): ?string {
        if ($referencesCurrentProduct) {
            return null;
        }

        if (! $explicitLookup && ! $availabilityIntent && ! $quantityIntent && $size === null) {
            return null;
        }

        $candidate = $this->removeSearchOpeners($normalized);
        $candidate = preg_replace('/\b(available|availability|in stock|stock|stocks|sold out|please|pls|na|ba|kayo|yung|ang|may|pa|for|does|do|have|nalang)\b/u', ' ', $candidate) ?? $candidate;
        $candidate = preg_replace('/\b(how many|ilan|pairs left|pair left|stocks left|stock left|left)\b/u', ' ', $candidate) ?? $candidate;
        $candidate = preg_replace('/\b(?:size|sz|us)\s*\d{1,2}(?:\.\d)?\b/u', ' ', $candidate) ?? $candidate;
        $candidate = preg_replace('/\b(?:under|below|less than|up to|within|over|above|more than|at least)\b.*$/u', ' ', $candidate) ?? $candidate;

        if ($color !== null) {
            $candidate = preg_replace('/\b'.preg_quote($color, '/').'\b/u', ' ', $candidate) ?? $candidate;
        }

        $candidate = trim((string) preg_replace('/\s+/u', ' ', $candidate));

        if ($candidate === '') {
            return null;
        }

        if (
            ($category !== null || $color !== null || $useCase !== null || $size !== null || $budgetMin !== null || $budgetMax !== null || $affordable || $premium)
            && ! $availabilityIntent
            && ! $quantityIntent
            && $size === null
        ) {
            if ($this->containsAny($candidate, self::PRODUCT_TERMS)) {
                return null;
            }
        }

        if (mb_strlen($candidate) < 3) {
            return null;
        }

        if (count($this->keywordsFromText($candidate)) === 0) {
            return null;
        }

        return Str::of($candidate)->title()->replaceMatches('/\s+/', ' ')->trim()->value();
    }

    private function extractSearchQuery(
        string $normalized,
        bool $referencesCurrentProduct,
        ?string $category,
        ?string $color,
        ?string $useCase,
        bool $affordable,
        bool $premium,
        ?float $budgetMin,
        ?float $budgetMax,
    ): ?string {
        if ($referencesCurrentProduct) {
            return null;
        }

        $query = $this->removeSearchOpeners($normalized);
        $query = preg_replace('/\b(please|pls|pre|bro|tol|boss|idol|sir|maam|mam)\b/u', ' ', $query) ?? $query;
        $query = trim((string) preg_replace('/\s+/u', ' ', $query));

        if ($query === '') {
            return null;
        }

        if ($category === null && $color === null && $useCase === null && ! $affordable && ! $premium && $budgetMin === null && $budgetMax === null) {
            return Str::of($query)->replaceMatches('/\s+/', ' ')->trim()->value();
        }

        return Str::of($query)
            ->replace([' na ', ' ba ', ' yung ', ' kayo '], ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();
    }

    private function normalizeCommerceLanguage(string $message): string
    {
        $patterns = [
            '/\bmeron ba kayo\b/u' => ' do you have ',
            '/\bmeron bang\b/u' => ' do you have ',
            '/\bmeron ba\b/u' => ' do you have ',
            '/\bmayroon ba kayong\b/u' => ' do you have ',
            '/\bmayroong\b/u' => ' may ',
            '/\bhanap moko\b/u' => ' find me ',
            '/\bhanap mo ko\b/u' => ' find me ',
            '/\bhanap ako\b/u' => ' find ',
            '/\bpahanap\b/u' => ' find ',
            '/\bhanapan\b/u' => ' find ',
            '/\bhanap\b/u' => ' find ',
            '/\bsapatos\b/u' => ' shoes ',
            '/\brubber shoes\b/u' => ' sneakers ',
            '/\bpanlalaki\b/u' => ' men ',
            '/\bpambabae\b/u' => ' women ',
            '/\bmura\b/u' => ' affordable ',
            '/\bmahal\b/u' => ' premium ',
            '/\bpang gym\b/u' => ' gym training ',
            '/\bpang hiking\b/u' => ' hiking trail boots ',
            '/\bpang casual\b/u' => ' casual daily ',
            '/\bpang trail\b/u' => ' hiking trail ',
            '/\bpang jogging\b/u' => ' running ',
            '/\bpang takbo\b/u' => ' running ',
            '/\bpang lakad\b/u' => ' walking ',
            '/\bpang\b/u' => ' for ',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message) ?? $message;
        }

        return trim((string) preg_replace('/\s+/u', ' ', $message));
    }

    private function stripLeadingFillers(string $message): string
    {
        $stripped = trim($message);

        do {
            $changed = false;

            foreach (self::LEADING_FILLERS as $filler) {
                if (str_starts_with($stripped, $filler.' ')) {
                    $stripped = trim(substr($stripped, strlen($filler)));
                    $changed = true;
                }
            }
        } while ($changed);

        return trim($stripped);
    }

    private function removeSearchOpeners(string $message): string
    {
        $query = trim($message);

        foreach (self::SEARCH_OPENERS as $opener) {
            if (str_starts_with($query, $opener.' ')) {
                return trim(substr($query, strlen($opener)));
            }
        }

        return $query;
    }

    private function hasSearchOpener(string $message): bool
    {
        foreach (self::SEARCH_OPENERS as $opener) {
            if (str_starts_with($message, $opener.' ') || $message === $opener) {
                return true;
            }
        }

        return false;
    }

    private function referencesCurrentProduct(string $message): bool
    {
        foreach (self::CURRENT_PRODUCT_PHRASES as $phrase) {
            if (str_contains($message, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function detectCategory(string $text): ?string
    {
        foreach (self::CATEGORY_KEYWORDS as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $category;
                }
            }
        }

        return null;
    }

    private function detectColor(string $text): ?string
    {
        foreach (self::COLOR_KEYWORDS as $color => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $color;
                }
            }
        }

        return null;
    }

    private function detectUseCase(string $text): ?string
    {
        return match (true) {
            str_contains($text, 'hiking') || str_contains($text, 'trail') || str_contains($text, 'boots') => 'hiking',
            str_contains($text, 'daily') || str_contains($text, 'everyday') || str_contains($text, 'casual') => 'daily',
            str_contains($text, 'running') || str_contains($text, 'runner') || str_contains($text, 'jog') => 'running',
            str_contains($text, 'walking') || str_contains($text, 'walk') => 'walking',
            str_contains($text, 'gym') || str_contains($text, 'training') || str_contains($text, 'workout') => 'gym',
            str_contains($text, 'performance') || str_contains($text, 'premium support') => 'performance',
            default => null,
        };
    }

    private function detectGender(string $text): ?string
    {
        return match (true) {
            str_contains($text, 'women') || str_contains($text, 'female') || str_contains($text, 'ladies') => 'women',
            str_contains($text, 'men') || str_contains($text, 'male') || str_contains($text, 'gentlemen') => 'men',
            default => null,
        };
    }

    private function extractSize(string $text): ?string
    {
        if (! preg_match('/(?:size|sz|us)\s*(\d{1,2}(?:\.\d)?)\b/i', $text, $matches)
            && ! preg_match('/\bmay size\s*(\d{1,2}(?:\.\d)?)\b/i', $text, $matches)
            && ! (
                $this->containsAny($text, array_merge(self::AVAILABILITY_KEYWORDS, self::QUANTITY_KEYWORDS))
                && preg_match('/\b(6|7|8|9|10|11|12)(?:\.5)?\b/i', $text, $matches)
            )) {
            return null;
        }

        return $this->normalizeSizeValue($matches[1]);
    }

    private function extractBudget(string $text): array
    {
        $budgetMin = null;
        $budgetMax = null;

        if (preg_match('/(?:under|below|less than|max(?:imum)?|up to|within)\s*(?:php|p|â‚±)?\s*([\d.,]+(?:\s*k)?)/i', $text, $matches)) {
            $budgetMax = $this->moneyValue($matches[1]);
        }

        if (preg_match('/(?:over|above|more than|min(?:imum)?|at least)\s*(?:php|p|â‚±)?\s*([\d.,]+(?:\s*k)?)/i', $text, $matches)) {
            $budgetMin = $this->moneyValue($matches[1]);
        }

        return [$budgetMin, $budgetMax];
    }

    private function moneyValue(string $value): ?float
    {
        $normalized = Str::lower(trim($value));
        $isThousands = str_ends_with($normalized, 'k');
        $normalized = str_replace([',', ' '], '', $normalized);
        $normalized = rtrim($normalized, 'k');

        if (! is_numeric($normalized)) {
            return null;
        }

        $amount = (float) $normalized;

        return $isThousands ? $amount * 1000 : $amount;
    }

    private function keywordsFromText(string $text): array
    {
        return collect(preg_split('/[^a-z0-9]+/i', Str::lower($text)) ?: [])
            ->filter(fn (string $token): bool => $token !== '' && strlen($token) > 1)
            ->values()
            ->all();
    }

    private function normalizeSizeValue(string $size): string
    {
        $size = trim($size);

        if (! str_contains($size, '.')) {
            return $size;
        }

        return rtrim(rtrim($size, '0'), '.');
    }

    private function normalizeText(string $message): string
    {
        $normalized = Str::lower($message);
        $normalized = str_replace(['sign-in', 'log-in'], ['sign in', 'log in'], $normalized);
        $normalized = preg_replace('/[^\pL\pN\s\.\-]+/u', ' ', $normalized) ?? $normalized;

        return trim((string) preg_replace('/\s+/u', ' ', $normalized));
    }

    private function containsAny(string $message, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isLowSignalText(string $message): bool
    {
        $message = trim($message);

        if ($message === '') {
            return true;
        }

        if (in_array($message, self::LOW_SIGNAL_PHRASES, true)) {
            return true;
        }

        if (preg_match('/^(ha)+$/i', $message) === 1) {
            return true;
        }

        if (preg_match('/^[a-z]{1,4}$/i', $message) === 1 && ! $this->containsAny($message, self::PRODUCT_TERMS)) {
            return true;
        }

        return preg_match('/^[a-z]{6,}$/i', $message) === 1
            && count($this->keywordsFromText($message)) === 1
            && ! $this->containsAny($message, self::PRODUCT_TERMS)
            && ! preg_match('/[0-9]/', $message);
    }
}
