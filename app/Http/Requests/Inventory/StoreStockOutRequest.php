<?php

namespace App\Http\Requests\Inventory;

use App\InventoryBatchQualityStatus;
use App\Models\InventoryBatch;
use App\ProcurementQuantity;
use Illuminate\Validation\Validator;

class StoreStockOutRequest extends InventoryRequest
{
    public function rules(): array
    {
        return [
            'inventory_batch_id' => ['required', 'integer', 'exists:inventory_batches,id'],
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'],
            'destination_location' => ['required', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['inventory_batch_id', 'quantity'])) {
                    return;
                }

                $batch = InventoryBatch::query()->find($this->integer('inventory_batch_id'));

                if ($batch === null) {
                    return;
                }

                if ($batch->quality_status !== InventoryBatchQualityStatus::Available
                    || ($batch->expires_at !== null && $batch->expires_at->isBefore(today()))) {
                    $validator->errors()->add('inventory_batch_id', 'Only available, unexpired batches can be issued.');

                    return;
                }

                $requestedMilli = ProcurementQuantity::toMilliUnits((string) $this->input('quantity'));

                if ($requestedMilli > $batch->quantityMilli()) {
                    $validator->errors()->add('quantity', 'Stock-out quantity cannot exceed the batch quantity on hand.');
                }
            },
        ];
    }
}
