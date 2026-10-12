<?php

namespace Database\Factories;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceiptItem>
 */
class GoodsReceiptItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'goods_receipt_id' => GoodsReceipt::factory(),
            'delivery_item_id' => null,
            'purchase_order_item_id' => null,
            'accepted_qty' => '1.000',
            'rejected_qty' => '0.000',
            'lot_code' => null,
            'expires_at' => null,
            'discrepancy_notes' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (GoodsReceiptItem $item): void {
            $receipt = GoodsReceipt::query()->findOrFail($item->goods_receipt_id);
            $deliveryItem = $receipt->delivery->items()->firstOrFail();
            $item->delivery_item_id = $deliveryItem->getKey();
            $item->purchase_order_item_id = $deliveryItem->purchase_order_item_id;
        });
    }
}
