<?php

namespace App\Http\Requests\Inventory;

class StoreUnitOfMeasureRequest extends InventoryRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', 'unique:units_of_measure,code'],
            'name' => ['required', 'string', 'max:255'],
            'decimal_places' => ['nullable', 'integer', 'between:0,3'],
        ];
    }
}
