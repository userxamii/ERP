<?php

namespace Database\Factories;

use App\MaterialStockReservationStatus;
use App\Models\Material;
use App\Models\MaterialStockReservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialStockReservation>
 */
class MaterialStockReservationFactory extends Factory
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
            'quantity' => '10.000',
            'status' => MaterialStockReservationStatus::Active,
            'source_reference' => fake()->bothify('REQ-########'),
            'created_by_user_id' => User::factory(),
        ];
    }
}
