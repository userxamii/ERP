<?php

namespace App\Http\Requests\Inventory;

class StoreMaterialStockReservationRequest extends InventoryRequest
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
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'status' => ['prohibited'],
            'created_by_user_id' => ['prohibited'],
            'released_by_user_id' => ['prohibited'],
            'released_at' => ['prohibited'],
        ];
    }
}
