<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryItem>
 */
class DeliveryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_id' => Delivery::factory(),
            'purchase_order_item_id' => null,
            'dispatched_qty' => '10.000',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (DeliveryItem $deliveryItem): void {
            $delivery = Delivery::query()->findOrFail($deliveryItem->delivery_id);
            $purchaseOrder = PurchaseOrder::query()->findOrFail($delivery->purchase_order_id);
            $purchaseOrderItem = $purchaseOrder->items()->firstOrFail();
            $deliveryItem->purchase_order_item_id = $purchaseOrderItem->getKey();
        });
    }
}
