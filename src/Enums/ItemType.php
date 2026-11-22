<?php

namespace WebdevsComAu\MachShip\Enums;

/**
 * MachShip ItemType (CreateConsignmentItemV2.itemType). Values from the MachShip API v2 Swagger.
 */
enum ItemType: int
{
    case Carton = 1;
    case Skid = 2;
    case Pallet = 3;
    case Crate = 4;
    case Satchel = 5;
    case Roll = 6;
    case Panel = 7;
    case Bag = 8;
    case Tube = 9;
    case Stillage = 10;
    case Envelope = 11;
    case Pack = 12;
    case Rails = 13;
    case TimberLoose = 14;
    case Combined = 15;
    case TimberPack = 16;
    case Pipe = 17;
    case BDouble = 18;
    case Semi = 19;
    case TwentyFootContainer = 20;
    case FortyFootContainer = 21;
    case Bundle = 22;
    case Case = 23;
    case Volume = 24;
    case IBC = 26;
    case GLPallet = 27;
    case GLTrolley = 28;
    case GLCarton = 29;
    case Trolley = 30;
    case TotalVolume = 31;
    case Drum = 32;
    case Loscam = 33;
    case LoscamWood = 34;
    case LoscamPlastic = 35;
    case Chep = 36;
    case ChepWood = 37;
    case ChepPlastic = 38;
    case Tray = 39;
    case Pot = 40;
    case SeedlingRack = 41;
    case SilverTrolley = 42;
    case LightTruckTyre = 43;
    case PassengerTyre = 44;
    case AgriculturalTyre = 45;
    case MowerTyre = 46;
    case SolidTyre = 47;
    case TractorTyre = 48;
    case TrailerTyre = 49;
    case TruckTyre = 50;
    case Pallecon = 51;
    case Item = 52;
    case Machine = 53;
    case JiffyBag = 54;
    case Pot200mm = 55;
    case Pot250mm = 56;
    case Pot300mm = 57;
    case Flatpack = 58;
    case BrandedPallet = 59;
    case Disposable = 60;
    case Plastic = 61;
    case MhpItem = 62;
    case Length = 63;
    case ChepIbc = 64;
    case LoscamIbc = 65;
    case ChepDisplay = 66;
    case LoscamDisplay = 67;
    case ChepM3Palletcon = 68;
    case LoscamM3Palletcon = 69;
    case ChepUnicon = 70;
    case LoscamUnicon = 71;
    case ChepIbcI6 = 72;
    case LoscamIbcI6 = 73;
    case FortyEightFootContainer = 74;
    case Bottle = 75;
}
