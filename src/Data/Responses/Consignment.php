<?php

namespace WebdevsComAu\MachShip\Data\Responses;

use WebdevsComAu\MachShip\Enums\TrackingStatus;

/**
 * A consignment, as returned by createConsignment or getConsignment.
 */
final class Consignment extends Data
{
    /** MachShip ID: store this, every other call uses it. */
    public int $id;

    /** MachShip reference, e.g. "MS00123456". */
    public ?string $consignmentNumber;

    /** The carrier's own connote / tracking number. */
    public ?string $carrierConsignmentId;

    public ?int $statusId;
    public ?string $statusName;
    public ?int $carrierId;
    public ?string $carrierName;
    public ?int $carrierServiceId;
    public ?string $carrierServiceName;
    public ?int $manifestId;
    public ?float $totalSellPrice;
    public ?string $despatchDateLocal;
    public ?string $etaLocal;
    public ?string $trackingPageAccessToken;
    public bool $isTest;

    public static function fromArray(array $attributes): static
    {
        $c = new self($attributes);

        $c->id = (int) ($attributes['id'] ?? 0);
        $c->consignmentNumber = $attributes['consignmentNumber'] ?? null;
        $c->carrierConsignmentId = $attributes['carrierConsignmentId'] ?? null;
        $c->statusId = self::int($attributes['status']['id'] ?? null);
        $c->statusName = $attributes['status']['name'] ?? null;
        $c->carrierId = self::int($attributes['carrier']['id'] ?? $attributes['carrierId'] ?? null);
        $c->carrierName = $attributes['carrier']['displayName'] ?? $attributes['carrierName'] ?? $attributes['carrier']['name'] ?? null;
        $c->carrierServiceId = self::int($attributes['carrierService']['id'] ?? $attributes['carrierServiceId'] ?? null);
        $c->carrierServiceName = $attributes['carrierService']['displayName'] ?? $attributes['carrierService']['name'] ?? null;
        $c->manifestId = self::int($attributes['manifestId'] ?? null);
        $c->totalSellPrice = self::float($attributes['consignmentTotal']['totalSellPrice'] ?? null);
        $c->despatchDateLocal = $attributes['despatchDateLocal'] ?? null;
        $c->etaLocal = $attributes['etaLocal'] ?? null;
        $c->trackingPageAccessToken = $attributes['trackingPageAccessToken'] ?? null;
        $c->isTest = (bool) ($attributes['isTest'] ?? false);

        return $c;
    }

    public function status(): ?TrackingStatus
    {
        return $this->statusId !== null ? TrackingStatus::tryFrom($this->statusId) : null;
    }

    public function isUnmanifested(): bool
    {
        return $this->status() === TrackingStatus::Unmanifested;
    }

    public function isManifested(): bool
    {
        return $this->manifestId !== null || ! in_array($this->status(), [null, TrackingStatus::Quote, TrackingStatus::Unmanifested], true);
    }
}
