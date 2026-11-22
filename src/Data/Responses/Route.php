<?php

namespace WebdevsComAu\MachShip\Data\Responses;

/**
 * A priced route (carrier + service + account) from routes/returnroutes.
 */
final class Route extends Data
{
    public ?int $carrierId;
    public ?string $carrierName;
    public ?int $carrierServiceId;
    public ?string $carrierServiceName;
    public ?int $carrierAccountId;
    public ?int $companyCarrierAccountId;
    public ?float $totalSellPrice;
    public ?float $totalSellBeforeTax;
    public ?float $totalCostPrice;
    public ?string $despatchDateLocal;
    public ?string $despatchDateUtc;
    public ?string $etaLocal;
    public ?string $etaUtc;
    public ?int $totalBusinessDays;

    public static function fromArray(array $attributes): static
    {
        $route = new self($attributes);
        $despatch = $attributes['despatchOptions'][0] ?? [];
        $total = $attributes['consignmentTotal'] ?? [];

        $route->carrierId = self::int($attributes['carrier']['id'] ?? null);
        $route->carrierName = $attributes['carrier']['displayName'] ?? $attributes['carrier']['name'] ?? null;
        $route->carrierServiceId = self::int($attributes['carrierService']['id'] ?? null);
        $route->carrierServiceName = $attributes['carrierService']['displayName'] ?? $attributes['carrierService']['name'] ?? null;
        $route->carrierAccountId = self::int($attributes['carrierAccount']['id'] ?? null);
        $route->companyCarrierAccountId = self::int($attributes['companyCarrierAccountId'] ?? null);
        $route->totalSellPrice = self::float($total['totalSellPrice'] ?? null);
        $route->totalSellBeforeTax = self::float($total['totalSellBeforeTax'] ?? null);
        $route->totalCostPrice = self::float($total['totalCostPrice'] ?? null);
        $route->despatchDateLocal = $despatch['despatchDateLocal'] ?? null;
        $route->despatchDateUtc = $despatch['despatchDateUtc'] ?? null;
        $route->etaLocal = $despatch['etaLocal'] ?? null;
        $route->etaUtc = $despatch['etaUtc'] ?? null;
        $route->totalBusinessDays = self::int($despatch['totalBusinessDays'] ?? null);

        return $route;
    }
}
