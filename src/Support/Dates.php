<?php

namespace WebdevsComAu\MachShip\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class Dates
{
    /**
     * Local (pickup time zone) date-time, sent without an offset.
     */
    public static function local(DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        return $value->format('Y-m-d\TH:i:s');
    }

    /**
     * UTC date-time with a trailing Z.
     */
    public static function utc(DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        return DateTimeImmutable::createFromInterface($value)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }
}
