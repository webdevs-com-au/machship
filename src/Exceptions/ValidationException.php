<?php

namespace WebdevsComAu\MachShip\Exceptions;

/**
 * MachShip rejected the request and returned one or more validation errors,
 * e.g. "From Location is invalid" or "No prices were found".
 */
class ValidationException extends MachShipException
{
}
