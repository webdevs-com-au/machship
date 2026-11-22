<?php

namespace WebdevsComAu\MachShip\Resources;

use Illuminate\Support\Collection;
use WebdevsComAu\MachShip\Data\Requests\ConsignmentRequest;
use WebdevsComAu\MachShip\Data\Responses\Route;

/**
 * Prices / routes. MachShip recommends these over the "quotes" endpoints,
 * which store a quote rather than just returning prices.
 */
class Routes extends Resource
{
    /**
     * All available routes for a shipment, cheapest first.
     *
     * @param  ConsignmentRequest|array<string, mixed>  $request  builder or raw RouteRequestV2 payload
     * @return Collection<int, Route>
     */
    public function get(ConsignmentRequest|array $request): Collection
    {
        [$endpoint, $payload] = $this->prepare($request);

        $result = $this->client->post($endpoint, $payload);

        return Route::collect($result['routes'] ?? [])
            ->sortBy(fn (Route $route) => $route->totalSellPrice ?? PHP_FLOAT_MAX)
            ->values();
    }

    public function cheapest(ConsignmentRequest|array $request): ?Route
    {
        return $this->get($request)->first();
    }

    public function fastest(ConsignmentRequest|array $request): ?Route
    {
        return $this->get($request)
            ->sortBy(fn (Route $route) => $route->etaUtc ?? '9999')
            ->first();
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function prepare(ConsignmentRequest|array $request): array
    {
        if ($request instanceof ConsignmentRequest) {
            $request->companyId ??= $this->defaultCompanyId;
            $complex = $request->requiresComplexItems();

            return [$complex ? 'routes/returnrouteswithcomplexitems' : 'routes/returnroutes', $request->toRoutePayload($complex)];
        }

        if ($this->defaultCompanyId !== null) {
            $request['companyId'] ??= $this->defaultCompanyId;
        }

        $complex = collect($request['items'] ?? [])->contains(fn ($item) => isset($item['standardItem']) || isset($item['consignmentItemDgItems']));

        return [$complex ? 'routes/returnrouteswithcomplexitems' : 'routes/returnroutes', $request];
    }
}
