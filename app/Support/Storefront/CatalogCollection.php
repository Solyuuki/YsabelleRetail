<?php

namespace App\Support\Storefront;

class CatalogCollection
{
    public const NEW_ARRIVALS = 'new-arrivals';

    public const BEST_SELLERS = 'best-sellers';

    public static function values(): array
    {
        return [
            self::NEW_ARRIVALS,
            self::BEST_SELLERS,
        ];
    }

    public static function metadata(?string $collection): ?array
    {
        return match ($collection) {
            self::NEW_ARRIVALS => [
                'label' => 'New Arrivals',
                'title' => 'New Arrivals',
                'description' => 'Fresh additions to the catalog, ordered by the newest product releases first.',
                'default_sort' => 'newest',
            ],
            self::BEST_SELLERS => [
                'label' => 'Best Sellers',
                'title' => 'Best Sellers',
                'description' => 'Top-performing styles ranked by completed, paid order units with real catalog popularity tie-breakers.',
                'default_sort' => 'best_sellers',
            ],
            default => null,
        };
    }

    public static function defaultSort(?string $collection): ?string
    {
        return self::metadata($collection)['default_sort'] ?? null;
    }

    public static function isNewArrivals(?string $collection): bool
    {
        return $collection === self::NEW_ARRIVALS;
    }

    public static function isBestSellers(?string $collection): bool
    {
        return $collection === self::BEST_SELLERS;
    }
}
