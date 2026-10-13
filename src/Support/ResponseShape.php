<?php

declare(strict_types=1);

namespace Digitonic\DrawHouse\Support;

/**
 * Helpers behind `drawhouse:fixtures`: redact PII from a recorded response
 * and describe a JSON body's SHAPE (keys and scalar types, recursively) so
 * two responses can be compared without comparing their values.
 *
 * @internal
 */
final class ResponseShape
{
    /**
     * Keys whose values are personal data, commercial terms or account
     * identifiers and must never be written to a fixture. Matched
     * case-insensitively on the key alone, at any depth.
     */
    public const REDACT_KEYS = [
        'email', 'phone', 'firstname', 'lastname', 'displayname', 'gender', 'dateofbirth',
        'accesstoken', 'refreshtoken', 'newaccesstoken', 'password',
        // Commercial terms from the partner agreement (/v2/partners/me).
        'pooledrevshare', 'ringfencedrevshare', 'minimumguaranteedincome',
        // Identifiers of the partner account that recorded the fixture.
        'companyname', 'affiliateid', 'playerid',
    ];

    /** Key prefixes redacted the same way (launchProposal* on /v2/partners/me). */
    public const REDACT_PREFIXES = ['launchproposal'];

    public const REDACTED = '[redacted]';

    /**
     * Replace PII and commercial values with a placeholder of the same JSON
     * type: strings become "[redacted]", numbers 0, booleans false, null
     * stays null. Structure is untouched.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function redact(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            // An object or list under a PII key (e.g. `consents.email`) is not
            // itself the value; descend into it instead of discarding it.
            if (is_string($key) && self::isRedactedKey($key) && ! is_array($value)) {
                $out[$key] = match (true) {
                    $value === null => null,
                    strtolower($key) === 'dateofbirth' => '1990-01-01',
                    is_string($value) => $value === '' ? '' : self::REDACTED,
                    is_bool($value) => false,
                    is_int($value) => 0,
                    is_float($value) => 0.0,
                    default => null,
                };

                continue;
            }

            $out[$key] = is_array($value) ? self::redact($value) : $value;
        }

        return $out;
    }

    /**
     * A flat `path => type` description of a body. Lists are described by
     * their first element under `[]`; empty lists are `list(empty)`. Types
     * are JSON types: null, bool, int, float, string, object, list.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<string, string>
     */
    public static function describe(array $data, string $prefix = ''): array
    {
        $shape = [];

        if (array_is_list($data)) {
            if ($data === []) {
                $shape[$prefix === '' ? '$' : $prefix] = 'list(empty)';

                return $shape;
            }

            $first = $data[0];
            $path = $prefix.'[]';

            if (is_array($first)) {
                return self::describe($first, $path);
            }

            $shape[$path] = self::type($first);

            return $shape;
        }

        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                if ($value === []) {
                    $shape[$path] = 'list(empty)';
                } elseif (array_is_list($value)) {
                    $shape[$path] = 'list';
                    $shape += self::describe($value, $path);
                } else {
                    $shape[$path] = 'object';
                    $shape += self::describe($value, $path);
                }

                continue;
            }

            $shape[$path] = self::type($value);
        }

        return $shape;
    }

    /**
     * Human-readable differences between a recorded shape and a live one.
     * A path present in both with different types is reported unless one
     * side is null (optional fields come and go), or one side is an empty
     * list and the other a list.
     *
     * @param  array<string, string>  $recorded
     * @param  array<string, string>  $live
     * @return list<string>
     */
    public static function diff(array $recorded, array $live): array
    {
        $out = [];

        foreach ($recorded as $path => $type) {
            if (! array_key_exists($path, $live)) {
                $out[] = sprintf('- %s (%s) is no longer returned', $path, $type);
            } elseif (! self::compatible($type, $live[$path])) {
                $out[] = sprintf('~ %s changed from %s to %s', $path, $type, $live[$path]);
            }
        }

        foreach ($live as $path => $type) {
            if (! array_key_exists($path, $recorded)) {
                $out[] = sprintf('+ %s (%s) is new', $path, $type);
            }
        }

        return $out;
    }

    private static function isRedactedKey(string $key): bool
    {
        $key = strtolower($key);

        if (in_array($key, self::REDACT_KEYS, true)) {
            return true;
        }

        foreach (self::REDACT_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function compatible(string $a, string $b): bool
    {
        if ($a === $b || $a === 'null' || $b === 'null') {
            return true;
        }

        $lists = ['list', 'list(empty)'];

        if (in_array($a, $lists, true) && in_array($b, $lists, true)) {
            return true;
        }

        // 10 and 10.0 are both "number" on the wire.
        return in_array($a, ['int', 'float'], true) && in_array($b, ['int', 'float'], true);
    }

    private static function type(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_string($value) => 'string',
            default => get_debug_type($value),
        };
    }
}
