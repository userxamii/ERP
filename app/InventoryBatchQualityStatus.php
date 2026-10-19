<?php

namespace App;

enum InventoryBatchQualityStatus: string
{
    case Available = 'available';
    case Held = 'held';
    case Rejected = 'rejected';
    case Damaged = 'damaged';
}
