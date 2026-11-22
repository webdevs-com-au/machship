<?php

namespace WebdevsComAu\MachShip\Enums;

/**
 * Broad lifecycle bucket of a consignment (ConsignmentV2.consignmentStatusType).
 */
enum ConsignmentStatusType: int
{
    case Quote = 1;
    case Unmanifested = 2;
    case Active = 3;
    case Completed = 4;
    case Deleted = 5;
    case Cancelled = 6;
}
