<?php

namespace App\Http\Requests\Inventory;

class StoreMaterialCategoryRequest extends InventoryRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:material_categories,name'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
