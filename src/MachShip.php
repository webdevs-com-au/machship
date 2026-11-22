<?php

namespace WebdevsComAu\MachShip;

use WebdevsComAu\MachShip\Resources\Companies;
use WebdevsComAu\MachShip\Resources\Consignments;
use WebdevsComAu\MachShip\Resources\Labels;
use WebdevsComAu\MachShip\Resources\Locations;
use WebdevsComAu\MachShip\Resources\Manifests;
use WebdevsComAu\MachShip\Resources\Routes;

/**
 * Entry point: MachShip::routes(), ::consignments(), ::labels(), ...
 */
class MachShip
{
    public function __construct(
        protected Client $client,
        protected ?int $defaultCompanyId = null,
        protected bool $printA4 = false,
    ) {
    }

    /**
     * Use a different token, e.g. per-tenant credentials.
     */
    public function withToken(string $token): static
    {
        return new static($this->client->withToken($token), $this->defaultCompanyId, $this->printA4);
    }

    /**
     * Default company for subsequent calls (where an endpoint accepts one).
     */
    public function forCompany(?int $companyId): static
    {
        return new static($this->client, $companyId, $this->printA4);
    }

    public function routes(): Routes
    {
        return new Routes($this->client, $this->defaultCompanyId);
    }

    public function consignments(): Consignments
    {
        return new Consignments($this->client, $this->defaultCompanyId);
    }

    public function labels(): Labels
    {
        return new Labels($this->client, $this->defaultCompanyId, $this->printA4);
    }

    public function manifests(): Manifests
    {
        return new Manifests($this->client, $this->defaultCompanyId);
    }

    public function companies(): Companies
    {
        return new Companies($this->client, $this->defaultCompanyId);
    }

    public function locations(): Locations
    {
        return new Locations($this->client, $this->defaultCompanyId);
    }

    /**
     * True when the token is valid (POST /apiv2/authenticate/ping).
     */
    public function ping(): bool
    {
        return (bool) $this->client->post('authenticate/ping');
    }

    public function client(): Client
    {
        return $this->client;
    }
}
