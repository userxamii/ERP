<?php

namespace App\Http\Requests\Procurement;

use App\DeliveryStatus;
use App\Http\Requests\Inventory\InventoryRequest;
use App\Models\Delivery;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDeliveryItemRequest extends InventoryRequest
{
    public function rules(): array
    {
        return [
            'delivery_id' => ['required', 'integer', 'exists:deliveries,id'],
            'purchase_order_item_id' => [
                'required',
                'integer',
                'exists:purchase_order_items,id',
                Rule::unique('delivery_items', 'purchase_order_item_id')
                    ->where(fn (Builder $query): Builder => $query->where('delivery_id', $this->input('delivery_id'))),
            ],
            'dispatched_qty' => [
                'required',
                'numeric',
                'decimal:0,3',
                'gt:0',
                'max:99999999999.999',
            ],
            'id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['delivery_id', 'purchase_order_item_id'])) {
                    return;
                }

                $delivery = Delivery::query()->find($this->integer('delivery_id'));
                $purchaseOrderItem = PurchaseOrderItem::query()->find($this->integer('purchase_order_item_id'));

                if ($delivery === null || $delivery->status !== DeliveryStatus::Draft) {
                    $validator->errors()->add('delivery_id', 'Items can only be added to a draft delivery.');

                    return;
                }

                if ($purchaseOrderItem !== null
                    && (int) $purchaseOrderItem->purchase_order_id !== (int) $delivery->purchase_order_id) {
                    $validator->errors()->add('purchase_order_item_id', 'The order item must belong to the selected delivery purchase order.');
                }
            },
        ];
    }
}
