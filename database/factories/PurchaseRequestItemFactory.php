<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequestItem>
 */
class PurchaseRequestItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'material_id' => Material::factory(),
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'requested_qty' => '1.000',
            'justification' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PurchaseRequestItem $purchaseRequestItem): void {
            $purchaseRequestItem->unit_of_measure_id = $purchaseRequestItem->material->unit_of_measure_id;
        });
    }
}
