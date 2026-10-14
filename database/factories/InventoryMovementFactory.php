<?php

namespace Database\Factories;

use App\InventoryMovementType;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
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
            'goods_receipt_item_id' => GoodsReceiptItem::factory(),
            'movement_type' => InventoryMovementType::GoodsReceiptRejected,
            'quantity' => '1.000',
            'on_hand_delta' => '0.000',
            'reserved_delta' => '0.000',
            'source_reference' => fake()->bothify('GR-########'),
            'actor_user_id' => User::factory(),
            'occurred_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (InventoryMovement $movement): void {
            $receiptItem = GoodsReceiptItem::query()->findOrFail($movement->goods_receipt_item_id);
            $movement->material_id = $receiptItem->purchaseOrderItem->material_id;
        });
    }
}
