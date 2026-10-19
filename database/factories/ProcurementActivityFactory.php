<?php

namespace Database\Factories;

use App\Models\ProcurementActivity;
use App\Models\User;
use App\ProcurementActivityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementActivity>
 */
class ProcurementActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'idempotency_key' => fake()->unique()->bothify('ACT-################'),
            'activity_type' => ProcurementActivityType::InventoryMovementRecorded,
            'actor_user_id' => User::factory(),
            'description' => fake()->sentence(),
            'metadata' => null,
            'occurred_at' => now(),
        ];
    }
}
