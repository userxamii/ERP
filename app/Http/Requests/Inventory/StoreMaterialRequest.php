<?php

namespace App\Http\Requests\Inventory;

class StoreMaterialRequest extends InventoryRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'unique:materials,code'],
            'name' => ['required', 'string', 'max:255'],
            'material_category_id' => ['nullable', 'integer', 'exists:material_categories,id'],
            'unit_of_measure_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'minimum_stock_qty' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:99999999999.999'],
            'reorder_to_qty' => [
                'required',
                'numeric',
                'decimal:0,3',
                'gte:minimum_stock_qty',
                'max:99999999999.999',
            ],
            'shelf_life_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
