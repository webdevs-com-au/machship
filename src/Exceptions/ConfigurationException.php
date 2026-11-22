<?php

namespace WebdevsComAu\MachShip\Exceptions;

class ConfigurationException extends MachShipException
{
    public static function missingToken(): self
    {
        return new self('No MachShip API token configured. Set MACHSHIP_TOKEN or call MachShip::withToken().');
    }
}
