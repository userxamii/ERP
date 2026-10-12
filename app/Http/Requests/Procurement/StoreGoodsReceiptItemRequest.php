<?php

namespace App\Http\Requests\Procurement;

use App\GoodsReceiptStatus;
use App\Http\Requests\Inventory\InventoryRequest;
use App\Models\DeliveryItem;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrderItem;
use App\ProcurementQuantity;
use Illuminate\Validation\Validator;

class StoreGoodsReceiptItemRequest extends InventoryRequest
{
    public function rules(): array
    {
        return [
            'goods_receipt_id' => ['required', 'integer', 'exists:goods_receipts,id'],
            'delivery_item_id' => ['required', 'integer', 'exists:delivery_items,id'],
            'purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'accepted_qty' => ['required', 'numeric', 'decimal:0,3', 'gte:0', 'max:99999999999.999'],
            'rejected_qty' => ['required', 'numeric', 'decimal:0,3', 'gte:0', 'max:99999999999.999'],
            'lot_code' => ['nullable', 'string', 'max:100'],
            'expires_at' => ['nullable', 'date'],
            'discrepancy_notes' => ['nullable', 'string', 'max:10000'],
            'id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny([
                    'goods_receipt_id',
                    'delivery_item_id',
                    'purchase_order_item_id',
                    'accepted_qty',
                    'rejected_qty',
                ])) {
                    return;
                }

                $receivedMilli = ProcurementQuantity::toMilliUnits((string) $this->input('accepted_qty'))
                    + ProcurementQuantity::toMilliUnits((string) $this->input('rejected_qty'));

                if ($receivedMilli <= 0) {
                    $validator->errors()->add('accepted_qty', 'At least one received quantity must be positive.');
                }

                if (ProcurementQuantity::toMilliUnits((string) $this->input('rejected_qty')) > 0
                    && trim((string) $this->input('discrepancy_notes')) === '') {
                    $validator->errors()->add('discrepancy_notes', 'Discrepancy notes are required for rejected quantities.');
                }

                $receipt = GoodsReceipt::query()->with('delivery')->find($this->integer('goods_receipt_id'));
                $deliveryItem = DeliveryItem::query()->find($this->integer('delivery_item_id'));
                $purchaseOrderItem = PurchaseOrderItem::query()->find($this->integer('purchase_order_item_id'));

                if ($receipt === null || $receipt->status !== GoodsReceiptStatus::Draft) {
                    $validator->errors()->add('goods_receipt_id', 'Receipt items can only be added to a draft goods receipt.');

                    return;
                }

                if ($deliveryItem === null
                    || (int) $deliveryItem->delivery_id !== (int) $receipt->delivery_id
                    || $purchaseOrderItem === null
                    || (int) $deliveryItem->purchase_order_item_id !== (int) $purchaseOrderItem->getKey()
                    || (int) $purchaseOrderItem->purchase_order_id !== (int) $receipt->purchase_order_id) {
                    $validator->errors()->add('delivery_item_id', 'Receipt item references must belong to the receipt delivery and purchase order.');

                    return;
                }

                if ($receivedMilli > $deliveryItem->remainingToReceiveMilli()
                    || $receivedMilli > $purchaseOrderItem->remainingToReceiveMilli()) {
                    $validator->errors()->add('accepted_qty', 'Received quantity cannot exceed remaining dispatched or ordered quantity.');
                }
            },
        ];
    }
}
