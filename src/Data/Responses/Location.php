<?php

namespace WebdevsComAu\MachShip\Data\Responses;

/**
 * A MachShip suburb/postcode location (LocationV2).
 */
final class Location extends Data
{
    public int $id;
    public ?string $suburb;
    public ?string $postcode;
    public ?string $state;
    public ?string $countryCode;
    public ?string $timeZone;

    public static function fromArray(array $attributes): static
    {
        $l = new self($attributes);
        $l->id = (int) ($attributes['id'] ?? 0);
        $l->suburb = $attributes['suburb'] ?? null;
        $l->postcode = $attributes['postcode'] ?? null;
        $l->state = $attributes['state']['code'] ?? null;
        $l->countryCode = $attributes['country']['code2'] ?? null;
        $l->timeZone = $attributes['timeZone'] ?? null;

        return $l;
    }
}
