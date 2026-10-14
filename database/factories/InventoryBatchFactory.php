<?php

namespace Database\Factories;

use App\InventoryBatchQualityStatus;
use App\Models\InventoryBatch;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryBatch>
 */
class InventoryBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'material_id' => Material::factory(),
            'lot_code' => fake()->unique()->bothify('LOT-########'),
            'received_at' => now()->subDays(14),
            'expires_at' => now()->addYear()->toDateString(),
            'quantity_received' => '100.000',
            'quantity_on_hand' => '100.000',
            'quality_status' => InventoryBatchQualityStatus::Available,
        ];
    }
}
