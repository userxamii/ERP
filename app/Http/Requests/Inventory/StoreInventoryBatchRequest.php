<?php

namespace App\Http\Requests\Inventory;

use App\InventoryBatchQualityStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class StoreInventoryBatchRequest extends InventoryRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'material_id' => ['required', 'integer', 'exists:materials,id'],
            'lot_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('inventory_batches', 'lot_code')
                    ->where(fn (Builder $query) => $query->where('material_id', $this->input('material_id'))),
            ],
            'received_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date'],
            'quantity_received' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'],
            'quantity_on_hand' => [
                'required',
                'numeric',
                'decimal:0,3',
                'min:0',
                'lte:quantity_received',
                'max:99999999999.999',
            ],
            'quality_status' => ['sometimes', Rule::enum(InventoryBatchQualityStatus::class)],
        ];
    }
}
