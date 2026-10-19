<?php

namespace App;

enum GoodsReceiptStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
}
