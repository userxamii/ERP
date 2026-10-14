<?php

namespace App;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case Cancelled = 'cancelled';
}
