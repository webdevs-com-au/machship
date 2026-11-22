<?php

namespace WebdevsComAu\MachShip\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \WebdevsComAu\MachShip\MachShip withToken(string $token)
 * @method static \WebdevsComAu\MachShip\MachShip forCompany(?int $companyId)
 * @method static \WebdevsComAu\MachShip\Resources\Routes routes()
 * @method static \WebdevsComAu\MachShip\Resources\Consignments consignments()
 * @method static \WebdevsComAu\MachShip\Resources\Labels labels()
 * @method static \WebdevsComAu\MachShip\Resources\Manifests manifests()
 * @method static \WebdevsComAu\MachShip\Resources\Companies companies()
 * @method static \WebdevsComAu\MachShip\Resources\Locations locations()
 * @method static bool ping()
 * @method static \WebdevsComAu\MachShip\Client client()
 *
 * @see \WebdevsComAu\MachShip\MachShip
 */
class MachShip extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \WebdevsComAu\MachShip\MachShip::class;
    }
}
