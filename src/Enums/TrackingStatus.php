<?php

namespace WebdevsComAu\MachShip\Enums;

/**
 * Detailed tracking status (ConsignmentV2.status.id). Values from the
 * MachShip API v2 Swagger; unknown future IDs map to null via tryFrom().
 */
enum TrackingStatus: int
{
    case Quote = 1;
    case Unmanifested = 2;
    case Manifested = 3;
    case Booked = 4;
    case InTransit = 5;
    case Delayed = 6;
    case Complete = 7;
    case Lost = 8;
    case Damaged = 9;
    case Cancelled = 10;
    case OnForDelivery = 13;
    case AddedToMachship = 14;
    case PickedUp = 15;
    case ScannedIntoDepot = 16;
    case DeliveryAttempted = 17;
    case SortedForDelivery = 18;
    case PartialDelivery = 19;
    case DeliveryTimeScheduled = 20;
    case Deleted = 21;
    case AtDelivery = 22;
    case PartialOnForDelivery = 23;
    case AtPickup = 24;
    case Manifesting = 25;
    case TrackingExpired = 26;
    case FailedPickup = 27;
    case FailedDelivery = 28;
    case AwaitingCollection = 29;
}
