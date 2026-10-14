<?php

namespace App;

enum DeliveryStatus: string
{
    case Draft = 'draft';
    case Dispatched = 'dispatched';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';
}
