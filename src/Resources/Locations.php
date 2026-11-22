<?php

namespace WebdevsComAu\MachShip\Resources;

use Illuminate\Support\Collection;
use WebdevsComAu\MachShip\Data\Responses\Location;
use WebdevsComAu\MachShip\Enums\CountryType;

/**
 * Suburb / postcode lookups. Use these to validate an address (or to let a
 * user choose a suburb) before quoting.
 */
class Locations extends Resource
{
    /**
     * Free-text search, e.g. "2000", "Sydney" or "Sydney 2000".
     *
     * @param  array<int, CountryType>  $countries
     * @return Collection<int, Location>
     */
    public function search(string $query, int $limit = 10, array $countries = [], ?int $companyId = null): Collection
    {
        $result = $this->client->post('locations/returnLocationsWithSearchOptions', array_filter([
            'companyId' => $this->companyId($companyId),
            'retrievalSize' => $limit,
            'countries' => $countries ? array_map(fn (CountryType $c) => $c->value, $countries) : null,
        ], fn ($v) => $v !== null), ['s' => $query]);

        return Location::collect($result);
    }

    /**
     * Resolve exact suburb + postcode pairs to MachShip locations.
     *
     * @param  array<int, array{suburb: string, postcode: string}>  $pairs
     * @return Collection<int, Location>
     */
    public function resolve(array $pairs): Collection
    {
        return Location::collect($this->client->post('locations/returnLocations', [
            'rawLocations' => array_values($pairs),
        ]));
    }

    /**
     * The first exact match for a suburb + postcode, or null.
     */
    public function find(string $suburb, string $postcode): ?Location
    {
        return $this->resolve([['suburb' => $suburb, 'postcode' => $postcode]])->first();
    }
}
