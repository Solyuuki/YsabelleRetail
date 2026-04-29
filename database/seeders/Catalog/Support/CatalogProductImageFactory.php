<?php

namespace Database\Seeders\Catalog\Support;

use GdImage;
use Illuminate\Support\Str;

final class CatalogProductImageFactory
{
    private const VERSION = 'v1';

    public static function build(string $categorySlug, string $categoryName, array $product, array $colors): array
    {
        $productSlug = Str::slug((string) ($product['name'] ?? 'product'));
        $styleCode = (string) ($product['style_code'] ?? $productSlug);
        $seed = substr(hash('sha256', $styleCode.'|'.$productSlug.'|'.$categorySlug), 0, 16);
        $palette = self::paletteFor($categorySlug, $colors[0] ?? null, $seed);

        $variants = [
            'primary' => ['scene' => 'hero', 'label' => 'primary'],
            'gallery-1' => ['scene' => 'studio', 'label' => 'gallery'],
            'gallery-2' => ['scene' => 'detail', 'label' => 'gallery'],
            'gallery-3' => ['scene' => 'lifestyle', 'label' => 'gallery'],
        ];

        $urls = [];

        foreach ($variants as $variantKey => $variant) {
            $relativePath = self::relativePathFor($categorySlug, $productSlug, $variantKey);

            self::renderImage(
                absolutePath: public_path($relativePath),
                categorySlug: $categorySlug,
                productName: (string) $product['name'],
                scene: $variant['scene'],
                palette: $palette,
                seed: $seed.'|'.$variantKey,
            );

            $urls[$variantKey] = $relativePath;
        }

        return [
            'primary_image_url' => $urls['primary'],
            'image_alt' => sprintf('%s %s product image', $product['name'], $categoryName),
            'image_gallery' => [
                $urls['gallery-1'],
                $urls['gallery-2'],
                $urls['gallery-3'],
            ],
        ];
    }

    private static function relativePathFor(string $categorySlug, string $productSlug, string $variantKey): string
    {
        if ($variantKey === 'primary') {
            return sprintf('images/products/%s/%s.png', $categorySlug, $productSlug);
        }

        return sprintf('images/products/%s/%s-%s.png', $categorySlug, $productSlug, $variantKey);
    }

    private static function renderImage(
        string $absolutePath,
        string $categorySlug,
        string $productName,
        string $scene,
        array $palette,
        string $seed,
    ): void {
        if (is_file($absolutePath)) {
            return;
        }

        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $width = 1280;
        $height = 960;
        $image = imagecreatetruecolor($width, $height);
        imageantialias($image, true);

        self::paintBackground($image, $scene, $palette);
        self::paintBackdropShape($image, $scene, $palette, $seed);
        self::paintShoeShadow($image, $scene);
        self::paintShoe($image, $categorySlug, $scene, $palette, $seed);
        self::paintSceneDetails($image, $scene, $palette, $seed, $productName);

        imagepng($image, $absolutePath);
        imagedestroy($image);
    }

    private static function paintBackground(GdImage $image, string $scene, array $palette): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        [$start, $end] = match ($scene) {
            'detail' => [$palette['background_soft'], $palette['background']],
            'lifestyle' => [$palette['background'], $palette['background_deep']],
            default => [$palette['background'], $palette['background_soft']],
        };

        for ($y = 0; $y < $height; $y++) {
            $mix = $y / max($height - 1, 1);
            [$red, $green, $blue] = self::mixHex($start, $end, $mix);
            $color = imagecolorallocate($image, $red, $green, $blue);
            imageline($image, 0, $y, $width, $y, $color);
        }
    }

    private static function paintBackdropShape(GdImage $image, string $scene, array $palette, string $seed): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $hash = hexdec(substr(hash('sha256', $seed), 0, 6));
        $ellipseColor = self::allocateHex($image, $palette['accent_soft']);
        $outline = self::allocateHex($image, $palette['background_deep']);
        imagefilledellipse(
            $image,
            (int) ($width * 0.72),
            (int) ($height * 0.26),
            (int) ($width * (0.34 + (($hash % 20) / 100))),
            (int) ($height * (0.18 + (($hash % 12) / 100))),
            $ellipseColor,
        );

        if ($scene === 'lifestyle') {
            imagesetthickness($image, 5);
            imagerectangle(
                $image,
                (int) ($width * 0.09),
                (int) ($height * 0.14),
                (int) ($width * 0.91),
                (int) ($height * 0.86),
                $outline,
            );
        }
    }

    private static function paintShoeShadow(GdImage $image, string $scene): void
    {
        $shadow = imagecolorallocatealpha($image, 40, 40, 40, 90);
        $x = $scene === 'detail' ? 690 : 640;
        $y = $scene === 'detail' ? 720 : 760;
        $width = $scene === 'detail' ? 520 : 620;
        $height = $scene === 'detail' ? 96 : 110;

        imagefilledellipse($image, $x, $y, $width, $height, $shadow);
    }

    private static function paintShoe(GdImage $image, string $categorySlug, string $scene, array $palette, string $seed): void
    {
        $scale = match ($scene) {
            'detail' => 1.18,
            'lifestyle' => 0.94,
            default => 1.0,
        };
        $offsetX = match ($scene) {
            'detail' => 74,
            'lifestyle' => 12,
            default => 0,
        };
        $offsetY = match ($scene) {
            'detail' => 4,
            'lifestyle' => -26,
            default => 0,
        };

        $upper = self::allocateHex($image, $palette['upper']);
        $upperDark = self::allocateHex($image, $palette['upper_dark']);
        $midsole = self::allocateHex($image, $palette['midsole']);
        $outsole = self::allocateHex($image, $palette['outsole']);
        $accent = self::allocateHex($image, $palette['accent']);
        $accentSoft = self::allocateHex($image, $palette['accent_soft']);
        $lace = self::allocateHex($image, $palette['lace']);

        $upperPoints = self::shapePoints($categorySlug, $scale, $offsetX, $offsetY);
        imagefilledpolygon($image, $upperPoints, (int) (count($upperPoints) / 2), $upper);
        imagepolygon($image, $upperPoints, (int) (count($upperPoints) / 2), $upperDark);

        $sole = self::soleRect($categorySlug, $scale, $offsetX, $offsetY);
        imagefilledrectangle($image, $sole['x1'], $sole['y1'], $sole['x2'], $sole['y2'], $midsole);
        imagefilledrectangle($image, $sole['x1'], $sole['y2'] - 24, $sole['x2'], $sole['y2'], $outsole);

        imagefilledellipse(
            $image,
            (int) (980 * $scale) + $offsetX,
            (int) (542 * $scale) + $offsetY,
            (int) (128 * $scale),
            (int) (72 * $scale),
            $upper,
        );

        imagefilledellipse(
            $image,
            (int) (300 * $scale) + $offsetX,
            (int) (590 * $scale) + $offsetY,
            (int) (122 * $scale),
            (int) (86 * $scale),
            $upperDark,
        );

        self::paintAccentPanel($image, $categorySlug, $scale, $offsetX, $offsetY, $accent, $accentSoft, $seed);
        self::paintLaces($image, $categorySlug, $scale, $offsetX, $offsetY, $lace);
        self::paintOutsoleTread($image, $categorySlug, $scale, $offsetX, $offsetY, $outsole);
    }

    private static function paintAccentPanel(GdImage $image, string $categorySlug, float $scale, int $offsetX, int $offsetY, int $accent, int $accentSoft, string $seed): void
    {
        $hash = hexdec(substr(hash('sha256', $seed), 0, 2));
        $style = $hash % 3;

        $points = match ($categorySlug) {
            'slip-ons' => [
                [470, 474], [646, 446], [772, 488], [722, 528], [554, 514],
            ],
            'boots-high-cut' => [
                [430, 408], [622, 368], [786, 446], [742, 548], [540, 554], [452, 510],
            ],
            default => [
                [456, 468], [596, 432], [782, 494], [748, 548], [532, 548], [454, 520],
            ],
        };

        imagefilledpolygon(
            $image,
            self::scaledPoints($points, $scale, $offsetX, $offsetY),
            count($points),
            $style === 2 ? $accentSoft : $accent,
        );

        if ($style !== 1) {
            imagefilledpolygon(
                $image,
                self::scaledPoints([
                    [560, 468], [670, 454], [748, 488], [722, 518], [600, 516],
                ], $scale, $offsetX, $offsetY),
                5,
                $style === 0 ? $accentSoft : $accent,
            );
        }
    }

    private static function paintLaces(GdImage $image, string $categorySlug, float $scale, int $offsetX, int $offsetY, int $lace): void
    {
        if ($categorySlug === 'slip-ons') {
            return;
        }

        imagesetthickness($image, max(3, (int) round(4 * $scale)));
        $lines = match ($categorySlug) {
            'boots-high-cut' => [
                [[478, 438], [586, 400]],
                [[486, 474], [606, 424]],
                [[500, 514], [630, 454]],
                [[520, 550], [660, 486]],
            ],
            default => [
                [[498, 470], [590, 432]],
                [[522, 500], [620, 454]],
                [[550, 530], [654, 478]],
            ],
        };

        foreach ($lines as [$start, $end]) {
            imageline(
                $image,
                (int) round(($start[0] * $scale) + $offsetX),
                (int) round(($start[1] * $scale) + $offsetY),
                (int) round(($end[0] * $scale) + $offsetX),
                (int) round(($end[1] * $scale) + $offsetY),
                $lace,
            );
        }
    }

    private static function paintOutsoleTread(GdImage $image, string $categorySlug, float $scale, int $offsetX, int $offsetY, int $outsole): void
    {
        imagesetthickness($image, max(2, (int) round(3 * $scale)));
        $startY = match ($categorySlug) {
            'boots-high-cut' => 632,
            default => 614,
        };

        for ($x = 360; $x <= 950; $x += 58) {
            imageline(
                $image,
                (int) round(($x * $scale) + $offsetX),
                (int) round(($startY * $scale) + $offsetY),
                (int) round((($x + 26) * $scale) + $offsetX),
                (int) round((($startY + 18) * $scale) + $offsetY),
                $outsole,
            );
        }
    }

    private static function paintSceneDetails(GdImage $image, string $scene, array $palette, string $seed, string $productName): void
    {
        $hash = hexdec(substr(hash('sha256', $seed.'|'.$productName), 0, 8));
        $lineColor = self::allocateHex($image, $palette['background_deep']);
        $dotColor = self::allocateHex($image, $palette['upper_dark']);
        $accentColor = self::allocateHex($image, $palette['accent']);
        $accentSoftColor = self::allocateHex($image, $palette['accent_soft']);

        if ($scene === 'studio') {
            imagesetthickness($image, 6);
            imageline($image, 84, 790, 1196, 790, $lineColor);
        }

        if ($scene === 'detail') {
            imagefilledellipse($image, 214, 236, 124, 124, self::allocateHex($image, $palette['accent']));
            imagesetthickness($image, 4);
            imagerectangle($image, 124, 148, 304, 328, $lineColor);
        }

        if ($scene === 'lifestyle') {
            for ($index = 0; $index < 6; $index++) {
                $x = 180 + (($hash + ($index * 73)) % 900);
                $y = 180 + (($hash + ($index * 41)) % 520);
                imagefilledellipse($image, $x, $y, 18, 18, $dotColor);
            }
        }

        imagesetthickness($image, 5);
        $markerOffset = $hash % 180;
        imageline($image, 130 + $markerOffset, 140, 290 + $markerOffset, 140, $accentColor);
        imageline($image, 130 + $markerOffset, 172, 250 + $markerOffset, 172, $accentSoftColor);

        if (($hash % 3) === 0) {
            imagefilledellipse($image, 1080, 160 + ($hash % 120), 26, 26, $accentColor);
        } elseif (($hash % 3) === 1) {
            imagefilledrectangle($image, 1040, 120 + ($hash % 110), 1080, 152 + ($hash % 110), $accentSoftColor);
        } else {
            imagerectangle($image, 1028, 118 + ($hash % 108), 1084, 174 + ($hash % 108), $accentColor);
        }
    }

    private static function shapePoints(string $categorySlug, float $scale, int $offsetX, int $offsetY): array
    {
        $points = match ($categorySlug) {
            'running' => [
                [242, 588], [350, 508], [468, 446], [634, 414], [820, 458], [956, 520], [1030, 572], [1016, 612], [930, 624], [794, 652], [558, 658], [346, 640],
            ],
            'sneakers' => [
                [248, 596], [344, 522], [478, 452], [658, 430], [816, 474], [964, 538], [1026, 588], [1014, 624], [904, 634], [768, 648], [522, 654], [326, 638],
            ],
            'basketball-shoes' => [
                [244, 608], [328, 446], [486, 348], [660, 332], [844, 396], [978, 514], [1034, 596], [1016, 634], [906, 648], [768, 664], [542, 676], [332, 652],
            ],
            'lifestyle-shoes' => [
                [248, 600], [344, 520], [474, 448], [650, 424], [830, 474], [964, 538], [1028, 590], [1012, 626], [896, 638], [748, 650], [516, 656], [324, 640],
            ],
            'training-shoes' => [
                [244, 600], [336, 514], [466, 440], [640, 402], [824, 448], [966, 522], [1032, 584], [1018, 622], [906, 636], [762, 654], [528, 662], [326, 644],
            ],
            'walking-shoes' => [
                [244, 604], [332, 518], [458, 442], [628, 416], [816, 452], [964, 520], [1032, 586], [1018, 622], [906, 638], [776, 652], [532, 662], [324, 644],
            ],
            'slip-ons' => [
                [246, 610], [342, 530], [470, 462], [640, 430], [830, 454], [972, 514], [1032, 582], [1018, 622], [900, 634], [758, 648], [524, 656], [320, 642],
            ],
            'boots-high-cut' => [
                [252, 620], [306, 414], [438, 318], [618, 300], [826, 380], [978, 510], [1042, 594], [1028, 638], [918, 654], [770, 670], [556, 682], [340, 658],
            ],
            default => [
                [248, 598], [340, 520], [470, 446], [640, 418], [820, 462], [964, 526], [1028, 586], [1014, 624], [902, 638], [764, 652], [530, 660], [330, 644],
            ],
        };

        return self::scaledPoints($points, $scale, $offsetX, $offsetY);
    }

    private static function soleRect(string $categorySlug, float $scale, int $offsetX, int $offsetY): array
    {
        $base = match ($categorySlug) {
            'basketball-shoes' => ['x1' => 278, 'y1' => 608, 'x2' => 1032, 'y2' => 652],
            'boots-high-cut' => ['x1' => 286, 'y1' => 622, 'x2' => 1040, 'y2' => 672],
            default => ['x1' => 286, 'y1' => 596, 'x2' => 1026, 'y2' => 638],
        };

        return [
            'x1' => (int) round(($base['x1'] * $scale) + $offsetX),
            'y1' => (int) round(($base['y1'] * $scale) + $offsetY),
            'x2' => (int) round(($base['x2'] * $scale) + $offsetX),
            'y2' => (int) round(($base['y2'] * $scale) + $offsetY),
        ];
    }

    private static function scaledPoints(array $points, float $scale, int $offsetX, int $offsetY): array
    {
        $scaled = [];

        foreach ($points as [$x, $y]) {
            $scaled[] = (int) round(($x * $scale) + $offsetX);
            $scaled[] = (int) round(($y * $scale) + $offsetY);
        }

        return $scaled;
    }

    private static function paletteFor(string $categorySlug, ?string $color, string $seed): array
    {
        $parts = collect(preg_split('/\s*\/\s*/', (string) $color) ?: [])
            ->map(fn (string $part): string => trim($part))
            ->filter()
            ->values();

        $base = self::colorHex($parts->get(0), self::defaultUpperFor($categorySlug));
        $accent = self::colorHex($parts->get(1), self::defaultAccentFor($categorySlug));
        $hash = hexdec(substr(hash('sha256', $seed), 0, 6));

        return [
            'upper' => self::shiftHex($base, -6 + ($hash % 8)),
            'upper_dark' => self::shiftHex($base, -34),
            'midsole' => self::shiftHex('#f7f3ec', ($hash % 10) - 4),
            'outsole' => self::shiftHex($base, -58),
            'lace' => self::shiftHex($base, -42),
            'accent' => self::shiftHex($accent, 0),
            'accent_soft' => self::shiftHex($accent, 26),
            'background' => self::shiftHex(self::backgroundFor($categorySlug), 8),
            'background_soft' => self::shiftHex(self::backgroundFor($categorySlug), 24),
            'background_deep' => self::shiftHex(self::backgroundFor($categorySlug), -16),
        ];
    }

    private static function defaultUpperFor(string $categorySlug): string
    {
        return match ($categorySlug) {
            'running' => '#23272f',
            'sneakers' => '#f0ede7',
            'basketball-shoes' => '#1f232c',
            'lifestyle-shoes' => '#736557',
            'training-shoes' => '#2c3137',
            'walking-shoes' => '#7a858d',
            'slip-ons' => '#8f8578',
            'boots-high-cut' => '#7f5a3c',
            default => '#2b2f35',
        };
    }

    private static function defaultAccentFor(string $categorySlug): string
    {
        return match ($categorySlug) {
            'running' => '#d0a236',
            'sneakers' => '#c7b08b',
            'basketball-shoes' => '#d5403b',
            'lifestyle-shoes' => '#b56d48',
            'training-shoes' => '#89b431',
            'walking-shoes' => '#6aa0cf',
            'slip-ons' => '#c8ad79',
            'boots-high-cut' => '#b37a46',
            default => '#d0a236',
        };
    }

    private static function backgroundFor(string $categorySlug): string
    {
        return match ($categorySlug) {
            'running' => '#e6ecef',
            'sneakers' => '#f2ede6',
            'basketball-shoes' => '#ece8e2',
            'lifestyle-shoes' => '#ede8e1',
            'training-shoes' => '#e8ece7',
            'walking-shoes' => '#edf0ec',
            'slip-ons' => '#f2eee7',
            'boots-high-cut' => '#ece7de',
            default => '#ece8e1',
        };
    }

    private static function colorHex(?string $name, string $fallback): string
    {
        $colors = [
            'black' => '#24262b',
            'graphite' => '#4d5560',
            'gold' => '#be9332',
            'blue' => '#3a5fcf',
            'navy' => '#293b66',
            'white' => '#f3f0ea',
            'ivory' => '#f0e7d7',
            'stone' => '#b7aa9b',
            'sand' => '#c6b28c',
            'platinum' => '#c8cbcf',
            'orange' => '#d96a33',
            'cinder' => '#6e6258',
            'moss' => '#708158',
            'slate' => '#63717b',
            'seafoam' => '#7ea59a',
            'pearl' => '#efe9dd',
            'bronze' => '#946444',
            'red' => '#c74239',
            'crimson' => '#ab2d2d',
            'royal' => '#2f58b8',
            'silver' => '#aab0b6',
            'olive' => '#6f7650',
            'mahogany' => '#814a3f',
            'taupe' => '#978173',
            'espresso' => '#5f4536',
            'grey' => '#8c939b',
            'gray' => '#8c939b',
            'rose' => '#c28c93',
            'tan' => '#b9875d',
            'chestnut' => '#8c5f38',
            'charcoal' => '#35393f',
            'ochre' => '#be8b2a',
            'volt' => '#a6c52a',
            'cream' => '#eee6d7',
            'canvas white' => '#f5f1e6',
            'clay' => '#b98d69',
            'willow' => '#7f8b63',
            'pearl' => '#f0e7d7',
            'mahogany' => '#814a3f',
        ];

        $normalized = Str::lower(trim((string) $name));

        return $colors[$normalized] ?? $fallback;
    }

    private static function shiftHex(string $hex, int $amount): string
    {
        [$red, $green, $blue] = self::hexToRgb($hex);

        return sprintf(
            '#%02x%02x%02x',
            self::clampChannel($red + $amount),
            self::clampChannel($green + $amount),
            self::clampChannel($blue + $amount),
        );
    }

    private static function mixHex(string $from, string $to, float $amount): array
    {
        [$fromRed, $fromGreen, $fromBlue] = self::hexToRgb($from);
        [$toRed, $toGreen, $toBlue] = self::hexToRgb($to);

        return [
            (int) round($fromRed + (($toRed - $fromRed) * $amount)),
            (int) round($fromGreen + (($toGreen - $fromGreen) * $amount)),
            (int) round($fromBlue + (($toBlue - $fromBlue) * $amount)),
        ];
    }

    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function clampChannel(int $value): int
    {
        return max(0, min(255, $value));
    }

    private static function allocateHex(GdImage $image, string $hex): int
    {
        [$red, $green, $blue] = self::hexToRgb($hex);

        return imagecolorallocate($image, $red, $green, $blue);
    }
}
