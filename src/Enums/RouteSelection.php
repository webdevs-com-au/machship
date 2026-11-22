<?php

namespace WebdevsComAu\MachShip\Enums;

/**
 * How MachShip picks a carrier when none is specified (defaultRouteSelection).
 */
enum RouteSelection: int
{
    case Cheapest = 0;
    case Fastest = 1;
}
