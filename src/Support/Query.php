<?php

namespace WebdevsComAu\MachShip\Support;

use BackedEnum;
use DateTimeInterface;

/**
 * Builds query strings the way ASP.NET model binding expects them:
 * booleans as "true"/"false" and arrays as repeated keys (ids=1&ids=2).
 */
final class Query
{
    /**
     * @param  array<string, mixed>  $params
     */
    public static function build(array $params): string
    {
        $parts = [];

        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }

            foreach (is_array($value) ? $value : [$value] as $item) {
                if ($item === null) {
                    continue;
                }

                $parts[] = rawurlencode((string) $key).'='.rawurlencode(self::scalar($item));
            }
        }

        return implode('&', $parts);
    }

    private static function scalar(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d\TH:i:s'),
            default => (string) $value,
        };
    }
}
