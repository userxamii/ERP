<?php

namespace App\Http\Requests\Procurement;

use App\CurrencyCodes;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierMaterialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'material_id' => [
                'required',
                'integer',
                'exists:materials,id',
                Rule::unique('supplier_materials', 'material_id')
                    ->where(fn (Builder $query) => $query->where('supplier_id', $this->input('supplier_id'))),
            ],
            'supplier_sku' => ['nullable', 'string', 'max:100'],
            'lead_time_days' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'minimum_order_qty' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'],
            'order_multiple_qty' => ['nullable', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'],
            'quoted_unit_price' => [
                'nullable',
                'numeric',
                'decimal:0,4',
                'min:0',
                'max:9999999999.9999',
                'required_with:currency_code',
            ],
            'currency_code' => [
                'nullable',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
                Rule::in(CurrencyCodes::ISO_4217),
                'required_with:quoted_unit_price',
            ],
            'is_preferred' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
