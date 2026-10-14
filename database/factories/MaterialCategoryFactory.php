<?php

namespace Database\Factories;

use App\Models\MaterialCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialCategory>
 */
class MaterialCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
        ];
    }
}
