<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Validation\Rule;

class StoreWarehouseReceiptRequest extends InventoryRequest
{
    public function rules(): array
    {
        return [
            'material_id' => ['required', 'integer', Rule::exists('materials', 'id')->where('is_active', true)],
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'lot_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('inventory_batches', 'lot_code')
                    ->where(fn ($query) => $query->where('material_id', $this->input('material_id'))),
            ],
            'quantity_received' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'],
            'received_at' => ['required', 'date'],
            'manufactured_at' => ['nullable', 'date', 'before_or_equal:received_at'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:manufactured_at'],
            'storage_location' => ['required', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
