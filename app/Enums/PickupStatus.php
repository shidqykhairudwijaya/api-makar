<?php

namespace App\Enums;

enum PickupStatus: string
{
    case Waiting = 'waiting';
    case PickedUp = 'picked_up';
    case OutOfStock = 'out_of_stock';
}
