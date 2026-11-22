<?php

namespace WebdevsComAu\MachShip\Data\Responses;

/**
 * Result of manifests/manifest (ReturnBookedManifestV2).
 */
final class Manifest extends Data
{
    public ?int $id;
    public ?int $companyId;

    /** @var array<int, int> */
    public array $consignmentIds;

    public ?string $carrierReference;
    public bool $bookingSuccessful;
    public ?string $errorMessage;

    public static function fromArray(array $attributes): static
    {
        $m = new self($attributes);
        $m->id = self::int($attributes['id'] ?? null);
        $m->companyId = self::int($attributes['companyId'] ?? null);
        $m->consignmentIds = array_map('intval', $attributes['consignmentIds'] ?? []);
        $m->carrierReference = $attributes['carrierReference'] ?? null;
        $m->bookingSuccessful = (bool) ($attributes['bookingSuccessful'] ?? false);
        $m->errorMessage = $attributes['errorMessage'] ?? null;

        return $m;
    }
}
