<?php

namespace WebdevsComAu\MachShip\Resources;

use WebdevsComAu\MachShip\Client;

abstract class Resource
{
    public function __construct(
        protected Client $client,
        protected ?int $defaultCompanyId = null,
    ) {
    }

    protected function companyId(?int $companyId): ?int
    {
        return $companyId ?? $this->defaultCompanyId;
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, int>
     */
    protected function ids(array $ids): array
    {
        return array_values(array_map('intval', $ids));
    }
}
