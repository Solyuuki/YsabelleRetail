<?php

namespace App\Support\Storefront;

use Illuminate\Support\Str;

class ColorFamilyNormalizer
{
    /**
     * @var array<string, array<int, string>>
     */
    private const PHRASE_MAP = [
        'off white' => ['white', 'ivory'],
        'off-white' => ['white', 'ivory'],
        'canvas white' => ['white'],
        'black' => ['black'],
        'onyx' => ['black'],
        'shadow' => ['black'],
        'white' => ['white'],
        'ivory' => ['ivory'],
        'cream' => ['ivory'],
        'beige' => ['ivory'],
        'blue' => ['blue'],
        'navy' => ['blue'],
        'azure' => ['blue'],
        'graphite' => ['graphite'],
        'grey' => ['graphite'],
        'gray' => ['graphite'],
        'charcoal' => ['graphite'],
        'slate' => ['graphite'],
        'cinder' => ['graphite'],
        'carbon' => ['graphite'],
        'gold' => ['gold'],
        'bronze' => ['gold'],
        'amber' => ['gold'],
        'tan' => ['gold'],
        'volt' => ['volt'],
        'lime' => ['volt'],
        'neon' => ['volt'],
        'green' => ['volt'],
    ];

    /**
     * @param  iterable<mixed>  $values
     * @return array<int, string>
     */
    public function familiesForValues(iterable $values): array
    {
        $families = [];

        foreach ($values as $value) {
            foreach ($this->familiesFromValue($value) as $family) {
                $families[$family] = true;
            }
        }

        return array_keys($families);
    }

    /**
     * @return array<int, string>
     */
    public function familiesFromValue(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }

        $normalized = Str::of($value)
            ->lower()
            ->replaceMatches('/[()_]+/', ' ')
            ->replaceMatches('/[^a-z0-9\/,\-\s]+/', ' ')
            ->squish()
            ->toString();

        if ($normalized === '') {
            return [];
        }

        $families = [];

        foreach (self::PHRASE_MAP as $phrase => $mappedFamilies) {
            if (! str_contains($normalized, $phrase)) {
                continue;
            }

            foreach ($mappedFamilies as $family) {
                $families[$family] = true;
            }
        }

        return array_keys($families);
    }

    public function matchesFamily(mixed $value, ?string $family): bool
    {
        if (! is_string($family) || trim($family) === '') {
            return false;
        }

        return in_array(Str::lower(trim($family)), $this->familiesFromValue($value), true);
    }
}
