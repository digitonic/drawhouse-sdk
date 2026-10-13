<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Support;

use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Lenient coercion of the loosely typed values DrawHouse returns.
 *
 * The API mixes strings and numbers for the same concepts ("0.20" vs 0.2,
 * "true" vs true) and uses three different date formats, so every DTO goes
 * through here rather than trusting the wire type.
 *
 * @internal
 */
final class Parse
{
    public static function string(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /**
     * Like string(), but treats an empty string as absent.
     */
    public static function nonEmptyString(mixed $value): ?string
    {
        $string = self::string($value);

        return $string === null || trim($string) === '' ? null : $string;
    }

    public static function int(mixed $value): ?int
    {
        if ($value === null || $value === '' || is_array($value) || is_object($value) || is_bool($value)) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    public static function float(mixed $value): ?float
    {
        if ($value === null || $value === '' || is_array($value) || is_object($value) || is_bool($value)) {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    public static function bool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (! is_string($value)) {
            return null;
        }

        return match (strtolower(trim($value))) {
            'true', '1', 'yes', 'y' => true,
            'false', '0', 'no', 'n', '' => false,
            default => null,
        };
    }

    /**
     * RFC 3339 / ISO 8601 timestamp, normalised to UTC.
     */
    public static function dateTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (InvalidFormatException) {
            return null;
        }
    }

    /**
     * `YYYY-MM-DD` date (used by Player.dateOfBirth).
     */
    public static function date(mixed $value): ?CarbonImmutable
    {
        return self::fromFormat('!Y-m-d', $value);
    }

    /**
     * `YYYYMMDD` compact date with no separators (used by Player.registrationDate).
     */
    public static function compactDate(mixed $value): ?CarbonImmutable
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        return self::fromFormat('!Ymd', $value);
    }

    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     */
    public static function enum(string $enum, mixed $value): ?BackedEnum
    {
        if ($value instanceof $enum) {
            return $value;
        }

        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $needle = is_string($value) ? strtoupper(str_replace(['-', ' '], '_', trim($value))) : $value;

        return $enum::tryFrom($needle);
    }

    /**
     * Pull a list of items out of an endpoint's `data`. Accepts bare arrays
     * and also tolerates `{ "<key>": [...] }` wrappers.
     *
     * @return list<array<string, mixed>>
     */
    public static function items(mixed $data, ?string $key = null): array
    {
        if (! is_array($data)) {
            return [];
        }

        if ($key !== null && array_key_exists($key, $data) && is_array($data[$key])) {
            $data = $data[$key];
        }

        if (! array_is_list($data)) {
            // A single object rather than a list.
            return [$data];
        }

        return array_values(array_filter($data, 'is_array'));
    }

    private static function fromFormat(string $format, mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        try {
            $parsed = CarbonImmutable::createFromFormat($format, $value, 'UTC');
        } catch (InvalidFormatException) {
            return null;
        }

        if (! $parsed instanceof CarbonImmutable) {
            return null;
        }

        // createFromFormat only WARNS on impossible calendar dates (2023-02-29
        // becomes 2023-03-01). Round-trip the value so we never fabricate a date.
        if ($parsed->format(ltrim($format, '!')) !== $value) {
            return null;
        }

        return $parsed;
    }
}
