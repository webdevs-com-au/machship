<?php

namespace WebdevsComAu\MachShip\Data\Responses;

/**
 * An address saved against a company (pickup/warehouse/customer).
 */
final class CompanyLocation extends Data
{
    public int $id;
    public ?string $name;
    public ?string $abbreviation;
    public ?string $addressLine1;
    public ?string $addressLine2;
    public ?string $suburb;
    public ?string $postcode;
    public ?int $locationId;

    public static function fromArray(array $attributes): static
    {
        $l = new self($attributes);
        $l->id = (int) ($attributes['id'] ?? 0);
        $l->name = $attributes['name'] ?? null;
        $l->abbreviation = $attributes['abbreviation'] ?? null;
        $l->addressLine1 = $attributes['addressLine1'] ?? null;
        $l->addressLine2 = $attributes['addressLine2'] ?? null;
        $l->suburb = $attributes['location']['suburb'] ?? null;
        $l->postcode = $attributes['location']['postcode'] ?? null;
        $l->locationId = self::int($attributes['locationId'] ?? null);

        return $l;
    }
}
