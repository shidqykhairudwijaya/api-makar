<?php

namespace App\Enums;

enum PickupStatus: string
{
    case Waiting = 'waiting';
    case Ready = 'ready';
    case PickedUp = 'picked_up';
    case OutOfStock = 'out_of_stock';
}
