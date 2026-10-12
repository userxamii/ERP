<?php

namespace App;

enum InventoryMovementType: string
{
    case GoodsReceiptAccepted = 'goods_receipt_accepted';
    case GoodsReceiptRejected = 'goods_receipt_rejected';
    case ReservationCreated = 'reservation_created';
    case ReservationReleased = 'reservation_released';
}
