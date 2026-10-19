<?php

namespace App\Http\Requests\Procurement;

use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequestItem;
use App\ProcurementQuantity;
use App\PurchaseOrderStatus;
use App\PurchaseRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseOrderAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'purchase_request_item_id' => [
                'required',
                'integer',
                'exists:purchase_request_items,id',
                Rule::unique('purchase_order_item_allocations', 'purchase_request_item_id')
                    ->where(fn ($query) => $query->where(
                        'purchase_order_item_id',
                        $this->input('purchase_order_item_id'),
                    )),
            ],
            'allocated_qty' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'],
            'status' => ['prohibited'],
            'purchase_order_id' => ['prohibited'],
            'purchase_request_id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny([
                    'purchase_order_item_id',
                    'purchase_request_item_id',
                    'allocated_qty',
                ])) {
                    return;
                }

                $purchaseOrderItem = PurchaseOrderItem::query()
                    ->with('purchaseOrder')
                    ->find($this->input('purchase_order_item_id'));
                $requestItem = PurchaseRequestItem::query()
                    ->with('purchaseRequest')
                    ->find($this->input('purchase_request_item_id'));

                if ($purchaseOrderItem === null || $requestItem === null) {
                    return;
                }

                if ($purchaseOrderItem->purchaseOrder->status !== PurchaseOrderStatus::Draft) {
                    $validator->errors()->add('purchase_order_item_id', 'Only draft purchase orders can receive allocations.');
                }

                if ($requestItem->purchaseRequest->status !== PurchaseRequestStatus::Approved) {
                    $validator->errors()->add(
                        'purchase_request_item_id',
                        'Only items from approved purchase requests can be ordered.',
                    );
                }

                if ((int) $purchaseOrderItem->material_id !== (int) $requestItem->material_id
                    || (int) $purchaseOrderItem->unit_of_measure_id !== (int) $requestItem->unit_of_measure_id) {
                    $validator->errors()->add(
                        'purchase_request_item_id',
                        'The approved request item must match the purchase order material and unit.',
                    );
                }

                if (ProcurementQuantity::toMilliUnits((string) $this->input('allocated_qty'))
                    > $requestItem->remainingQuantityMilli()) {
                    $validator->errors()->add(
                        'allocated_qty',
                        'The allocated quantity cannot exceed the unallocated approved request quantity.',
                    );
                }
            },
        ];
    }
}
