<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Throwable;

class BusinessTime
{
    public static function businessTimezone(): string
    {
        return (string) config('app.business_timezone', self::storageTimezone());
    }

    public static function storageTimezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::businessTimezone());
    }

    public static function format(
        mixed $value,
        string $format = 'M d, Y h:i A',
        string $fallback = '-',
    ): string {
        $date = self::toBusiness($value);

        return $date?->format($format) ?? $fallback;
    }

    public static function formatNow(string $format = 'M d, Y h:i A'): string
    {
        return self::now()->format($format);
    }

    public static function toBusiness(mixed $value): ?CarbonImmutable
    {
        $date = self::resolve($value);

        return $date?->setTimezone(self::businessTimezone());
    }

    public static function startOfBusinessDayInStorage(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::businessTimezone())
            ->startOfDay()
            ->setTimezone(self::storageTimezone());
    }

    public static function endOfBusinessDayInStorage(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::businessTimezone())
            ->endOfDay()
            ->setTimezone(self::storageTimezone());
    }

    private static function resolve(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        try {
            return CarbonImmutable::parse((string) $value, self::storageTimezone());
        } catch (Throwable) {
            return null;
        }
    }
}
