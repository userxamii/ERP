<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('MAT-#######'),
            'name' => fake()->words(2, true),
            'material_category_id' => MaterialCategory::factory(),
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'minimum_stock_qty' => '100.000',
            'reorder_to_qty' => '200.000',
            'shelf_life_days' => 365,
            'is_active' => true,
        ];
    }
}
