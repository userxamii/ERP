<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\Supplier;
use App\Models\SupplierMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierMaterial>
 */
class SupplierMaterialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'material_id' => Material::factory(),
            'supplier_sku' => fake()->optional()->bothify('SKU-########'),
            'lead_time_days' => fake()->numberBetween(0, 45),
            'minimum_order_qty' => '10.000',
            'order_multiple_qty' => null,
            'quoted_unit_price' => '12.3456',
            'currency_code' => 'USD',
            'is_preferred' => false,
            'is_active' => true,
        ];
    }
}
