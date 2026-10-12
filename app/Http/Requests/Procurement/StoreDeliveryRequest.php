<?php

namespace App\Http\Requests\Procurement;

use App\Http\Requests\Inventory\InventoryRequest;
use App\Models\PurchaseOrder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDeliveryRequest extends InventoryRequest
{
    public function rules(): array
    {
        return [
            'delivery_number' => ['required', 'string', 'max:64', 'unique:deliveries,delivery_number'],
            'purchase_order_id' => [
                'required',
                'integer',
                Rule::exists('purchase_orders', 'id')->where('status', 'ordered'),
            ],
            'supplier_delivery_reference' => ['nullable', 'string', 'max:100'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'dispatch_date' => ['nullable', 'date'],
            'expected_arrival_date' => ['nullable', 'date', 'after_or_equal:dispatch_date'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'supplier_id' => ['prohibited'],
            'status' => ['prohibited'],
            'created_by_user_id' => ['prohibited'],
            'dispatched_at' => ['prohibited'],
            'actual_arrival_at' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('purchase_order_id')) {
                    return;
                }

                $purchaseOrder = PurchaseOrder::query()
                    ->with('supplier')
                    ->find($this->integer('purchase_order_id'));

                if ($purchaseOrder !== null && ! $purchaseOrder->supplier->is_active) {
                    $validator->errors()->add('purchase_order_id', 'The purchase order supplier must be active.');
                }
            },
        ];
    }
}
