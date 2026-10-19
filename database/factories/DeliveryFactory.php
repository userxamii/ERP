<?php

namespace Database\Factories;

use App\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_number' => fake()->unique()->bothify('DEL-########'),
            'purchase_order_id' => PurchaseOrder::factory()->ordered(),
            'supplier_delivery_reference' => fake()->optional()->bothify('SHP-########'),
            'tracking_number' => fake()->optional()->bothify('TRK-########'),
            'dispatch_date' => now()->toDateString(),
            'expected_arrival_date' => now()->addDays(3)->toDateString(),
            'status' => DeliveryStatus::Draft,
            'notes' => null,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Delivery $delivery): void {
            $purchaseOrder = PurchaseOrder::query()->findOrFail($delivery->purchase_order_id);
            $delivery->supplier_id = $purchaseOrder->supplier_id;
        });
    }

    public function dispatched(): static
    {
        return $this->afterCreating(function (Delivery $delivery): void {
            DeliveryItem::factory()->for($delivery)->create();
            $delivery->dispatch($delivery->creator);
        });
    }
}
