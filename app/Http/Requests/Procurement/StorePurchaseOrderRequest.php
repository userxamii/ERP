<?php

namespace App\Http\Requests\Procurement;

use App\CurrencyCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', 'max:64', 'unique:purchase_orders,order_number'],
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where('is_active', true),
            ],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'currency_code' => ['required', 'string', 'size:3', Rule::in(CurrencyCodes::ISO_4217)],
            'notes' => ['nullable', 'string', 'max:10000'],
            'status' => ['prohibited'],
            'created_by_user_id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }
}
