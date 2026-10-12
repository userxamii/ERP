<?php

namespace App\Http\Requests\Procurement;

use App\Models\Material;
use App\PurchaseRequestStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseRequestItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'purchase_request_id' => [
                'required',
                'integer',
                Rule::exists('purchase_requests', 'id')
                    ->where('status', PurchaseRequestStatus::Draft->value),
            ],
            'material_id' => [
                'required',
                'integer',
                'exists:materials,id',
                Rule::unique('purchase_request_items', 'material_id')
                    ->where(fn (Builder $query) => $query->where(
                        'purchase_request_id',
                        $this->input('purchase_request_id'),
                    )),
            ],
            'unit_of_measure_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'requested_qty' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'],
            'justification' => ['nullable', 'string', 'max:10000'],
            'status' => ['prohibited'],
            'approver_user_id' => ['prohibited'],
            'decided_at' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['material_id', 'unit_of_measure_id'])) {
                    return;
                }

                $material = Material::query()->find($this->input('material_id'));

                if ($material !== null && (int) $this->input('unit_of_measure_id') !== $material->unit_of_measure_id) {
                    $validator->errors()->add(
                        'unit_of_measure_id',
                        'The unit of measure must match the selected material.',
                    );
                }
            },
        ];
    }
}
