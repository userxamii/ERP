<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'material_id' => Material::factory(),
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'supplier_material_id' => null,
            'ordered_qty' => '1.000',
            'unit_price' => '1.0000',
            'line_total' => '1.0000000',
            'notes' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PurchaseOrderItem $purchaseOrderItem): void {
            $purchaseOrderItem->unit_of_measure_id = $purchaseOrderItem->material->unit_of_measure_id;
        });
    }
}
