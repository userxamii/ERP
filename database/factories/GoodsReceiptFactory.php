<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\GoodsReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceipt>
 */
class GoodsReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_number' => fake()->unique()->bothify('GR-########'),
            'delivery_id' => Delivery::factory()->dispatched(),
            'received_by_user_id' => User::factory(),
            'notes' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (GoodsReceipt $receipt): void {
            $delivery = Delivery::query()->findOrFail($receipt->delivery_id);
            $receipt->purchase_order_id = $delivery->purchase_order_id;
        });
    }
}
