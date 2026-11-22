<?php

namespace WebdevsComAu\MachShip\Resources;

use Illuminate\Support\Collection;
use WebdevsComAu\MachShip\Data\Responses\Carrier;
use WebdevsComAu\MachShip\Data\Responses\Company;
use WebdevsComAu\MachShip\Data\Responses\CompanyLocation;

class Companies extends Resource
{
    /**
     * Every company the token can access.
     *
     * @return Collection<int, Company>
     */
    public function all(?int $atOrBelowCompanyId = null): Collection
    {
        return Company::collect($this->client->get('companies/getAll', ['atOrBelowCompanyId' => $atOrBelowCompanyId]));
    }

    /**
     * Carriers available to a company, with their accounts and services.
     * Use these IDs for ConsignmentRequest::carrier().
     *
     * @return Collection<int, Carrier>
     */
    public function carriers(?int $companyId = null): Collection
    {
        return Carrier::collect($this->client->get('companies/getAvailableCarriersAccountsAndServices', [
            'companyId' => $this->companyId($companyId),
        ]));
    }

    /**
     * Saved addresses for a company; use Address::companyLocation($id).
     *
     * @return Collection<int, CompanyLocation>
     */
    public function locations(?int $companyId = null): Collection
    {
        return CompanyLocation::collect($this->client->get('companyLocations/getAll', [
            'companyId' => $this->companyId($companyId),
        ]));
    }

    public function location(int $companyLocationId): CompanyLocation
    {
        return CompanyLocation::fromArray($this->client->get('companyLocations/get', ['id' => $companyLocationId]) ?? []);
    }
}
