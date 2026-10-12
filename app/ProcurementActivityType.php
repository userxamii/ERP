<?php

namespace App;

enum ProcurementActivityType: string
{
    case PurchaseRequestCreated = 'purchase_request_created';
    case PurchaseRequestSubmitted = 'purchase_request_submitted';
    case PurchaseRequestApproved = 'purchase_request_approved';
    case PurchaseRequestRejected = 'purchase_request_rejected';
    case PurchaseRequestCancelled = 'purchase_request_cancelled';
    case PurchaseOrderCreated = 'purchase_order_created';
    case PurchaseOrderPlaced = 'purchase_order_placed';
    case PurchaseOrderCancelled = 'purchase_order_cancelled';
    case DeliveryCreated = 'delivery_created';
    case DeliveryDispatched = 'delivery_dispatched';
    case DeliveryCancelled = 'delivery_cancelled';
    case GoodsReceiptPosted = 'goods_receipt_posted';
    case InventoryMovementRecorded = 'inventory_movement_recorded';
    case StockReservationCreated = 'stock_reservation_created';
    case StockReservationReleased = 'stock_reservation_released';
}
