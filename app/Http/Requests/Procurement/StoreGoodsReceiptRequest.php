<?php

namespace App\Http\Requests\Procurement;

use App\Http\Requests\Inventory\InventoryRequest;
use App\Models\Delivery;
use App\PurchaseOrderStatus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGoodsReceiptRequest extends InventoryRequest
{
    public function rules(): array
    {
        return [
            'receipt_number' => ['required', 'string', 'max:64', 'unique:goods_receipts,receipt_number'],
            'delivery_id' => [
                'required',
                'integer',
                Rule::exists('deliveries', 'id')->whereIn('status', ['dispatched', 'partially_received']),
            ],
            'notes' => ['nullable', 'string', 'max:10000'],
            'purchase_order_id' => ['prohibited'],
            'received_by_user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'received_at' => ['prohibited'],
            'posted_at' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('delivery_id')) {
                    return;
                }

                $delivery = Delivery::query()
                    ->with('purchaseOrder')
                    ->find($this->integer('delivery_id'));

                if ($delivery !== null && $delivery->purchaseOrder->status !== PurchaseOrderStatus::Ordered) {
                    $validator->errors()->add('delivery_id', 'The delivery purchase order must still be ordered.');
                }
            },
        ];
    }
}
