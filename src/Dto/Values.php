<?php

declare(strict_types=1);

namespace Vettore\Dto;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/** Lettura tipizzata e tollerante dai payload JSON: niente notice su chiavi assenti, niente cast impliciti sbagliati. */
final class Values
{
    /** @param  array<string, mixed>  $data */
    public static function string(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    /** @param  array<string, mixed>  $data */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /** @param  array<string, mixed>  $data */
    public static function bool(array $data, string $key, bool $default = false): bool
    {
        $value = $data[$key] ?? null;

        return is_bool($value) ? $value : (is_scalar($value) ? (bool) $value : $default);
    }

    /** @param  array<string, mixed>  $data */
    public static function int(array $data, string $key, int $default = 0): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /** @param  array<string, mixed>  $data */
    public static function nullableFloat(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function array(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public static function nullableArray(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function stringList(array $data, string $key): array
    {
        $out = [];
        foreach (self::array($data, $key) as $item) {
            if (is_scalar($item)) {
                $out[] = (string) $item;
            }
        }

        return $out;
    }

    /**
     * Date ISO 8601 (UTC con Z) e date semplici YYYY-MM-DD (lette come mezzanotte Europe/Rome, come le intende l'API).
     *
     * @param  array<string, mixed>  $data
     */
    public static function dateTime(array $data, string $key): ?DateTimeImmutable
    {
        $value = $data[$key] ?? null;
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return new DateTimeImmutable($value, new DateTimeZone('Europe/Rome'));
            }

            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }
}
