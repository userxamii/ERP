<?php

namespace App;

enum MaterialStockReservationStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Consumed = 'consumed';
}
