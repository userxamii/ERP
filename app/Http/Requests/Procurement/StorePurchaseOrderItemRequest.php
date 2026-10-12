<?php

namespace App\Http\Requests\Procurement;

use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\SupplierMaterial;
use App\ProcurementQuantity;
use App\PurchaseOrderStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => [
                'required',
                'integer',
                Rule::exists('purchase_orders', 'id')->where('status', PurchaseOrderStatus::Draft->value),
            ],
            'material_id' => [
                'required',
                'integer',
                'exists:materials,id',
                Rule::unique('purchase_order_items', 'material_id')
                    ->where(fn (Builder $query) => $query->where(
                        'purchase_order_id',
                        $this->input('purchase_order_id'),
                    )),
            ],
            'unit_of_measure_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'supplier_material_id' => [
                'nullable',
                'integer',
                Rule::exists('supplier_materials', 'id')->where('is_active', true),
            ],
            'ordered_qty' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'],
            'unit_price' => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999.9999'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'line_total' => ['prohibited'],
            'status' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['purchase_order_id', 'material_id'])) {
                    return;
                }

                $purchaseOrder = PurchaseOrder::query()->find($this->input('purchase_order_id'));
                $material = Material::query()->find($this->input('material_id'));

                if ($purchaseOrder === null || $material === null) {
                    return;
                }

                if (! $material->is_active) {
                    $validator->errors()->add('material_id', 'The selected material is inactive.');
                }

                if (! $validator->errors()->has('unit_of_measure_id')
                    && (int) $this->input('unit_of_measure_id') !== (int) $material->unit_of_measure_id) {
                    $validator->errors()->add(
                        'unit_of_measure_id',
                        'The unit of measure must match the selected material.',
                    );
                }

                if ($this->filled('supplier_material_id')
                    && ! $validator->errors()->has('supplier_material_id')) {
                    $supplierMaterial = SupplierMaterial::query()->find($this->input('supplier_material_id'));

                    if ($supplierMaterial === null
                        || (int) $supplierMaterial->supplier_id !== (int) $purchaseOrder->supplier_id
                        || (int) $supplierMaterial->material_id !== (int) $material->getKey()) {
                        $validator->errors()->add(
                            'supplier_material_id',
                            'Supplier-material terms must match the purchase order supplier and material.',
                        );

                        return;
                    }

                    if ($supplierMaterial->currency_code !== null
                        && $supplierMaterial->currency_code !== $purchaseOrder->currency_code) {
                        $validator->errors()->add(
                            'unit_price',
                            'The purchase order currency must match the supplier-material terms.',
                        );
                    }

                    if ($validator->errors()->has('ordered_qty')) {
                        return;
                    }

                    $orderedMilli = ProcurementQuantity::toMilliUnits((string) $this->input('ordered_qty'));
                    $minimumMilli = ProcurementQuantity::toMilliUnits((string) $supplierMaterial->minimum_order_qty);

                    if ($orderedMilli < $minimumMilli) {
                        $validator->errors()->add(
                            'ordered_qty',
                            'The ordered quantity cannot be less than the supplier minimum order quantity.',
                        );
                    }

                    if ($supplierMaterial->order_multiple_qty !== null) {
                        $multipleMilli = ProcurementQuantity::toMilliUnits((string) $supplierMaterial->order_multiple_qty);

                        if ($multipleMilli > 0 && $orderedMilli % $multipleMilli !== 0) {
                            $validator->errors()->add(
                                'ordered_qty',
                                'The ordered quantity must follow the supplier order quantity increment.',
                            );
                        }
                    }
                }
            },
        ];
    }
}
