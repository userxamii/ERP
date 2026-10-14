<?php

use App\InventoryBatchQualityStatus;
use App\InventoryMovementType;
use App\MaterialStockReservationStatus;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\MaterialStockReservation;
use App\Models\User;

function stage6DispatchedDelivery(): array
{
    $delivery = Delivery::factory()->create();
    $orderItem = $delivery->purchaseOrder->items()->firstOrFail();
    $deliveryItem = DeliveryItem::factory()->for($delivery)->create([
        'purchase_order_item_id' => $orderItem->getKey(),
        'dispatched_qty' => '10.000',
    ]);
    $delivery->dispatch(User::factory()->create());

    return [$delivery, $deliveryItem, $orderItem];
}

test('posting receipts records accepted and rejected inventory movements atomically', function () {
    [$delivery, $deliveryItem, $orderItem] = stage6DispatchedDelivery();
    $receipt = GoodsReceipt::factory()->create([
        'delivery_id' => $delivery->getKey(),
        'purchase_order_id' => $delivery->purchase_order_id,
    ]);
    $receiptItem = GoodsReceiptItem::factory()->for($receipt)->create([
        'delivery_item_id' => $deliveryItem->getKey(),
        'purchase_order_item_id' => $orderItem->getKey(),
        'accepted_qty' => '6.000',
        'rejected_qty' => '2.000',
        'discrepancy_notes' => 'Two units had damaged packaging.',
    ]);
    $actor = User::factory()->create();

    $receipt->post($actor);

    $movements = $receiptItem->inventoryMovements()->orderBy('movement_type')->get();

    expect($movements)->toHaveCount(2)
        ->and($movements->firstWhere('movement_type', InventoryMovementType::GoodsReceiptAccepted)->on_hand_delta)->toBe('6.000')
        ->and($movements->firstWhere('movement_type', InventoryMovementType::GoodsReceiptRejected)->on_hand_delta)->toBe('0.000')
        ->and($receiptItem->inventoryBatch->quantity_on_hand)->toBe('6.000')
        ->and($receiptItem->inventoryBatch->receivedBy->is($actor))->toBeTrue()
        ->and($receipt->procurementActivities()->where('activity_type', 'goods_receipt_posted')->exists())->toBeTrue();

    $movementCount = InventoryMovement::query()->count();
    $batchCount = InventoryBatch::query()->count();
    $receipt->post($actor);

    expect(InventoryMovement::query()->count())->toBe($movementCount)
        ->and(InventoryBatch::query()->count())->toBe($batchCount);
});

test('reservation creation and release are reflected in append-only inventory movements', function () {
    $material = Material::factory()->create();
    $reservation = MaterialStockReservation::factory()->for($material)->create([
        'quantity' => '3.250',
    ]);
    $actor = User::factory()->create();

    $reservation->release($actor);

    $movements = $reservation->movements()->orderBy('movement_type')->get();

    expect($reservation->fresh()->status)->toBe(MaterialStockReservationStatus::Released)
        ->and($movements)->toHaveCount(2)
        ->and($movements->firstWhere('movement_type', InventoryMovementType::ReservationCreated)->reserved_delta)->toBe('3.250')
        ->and($movements->firstWhere('movement_type', InventoryMovementType::ReservationReleased)->reserved_delta)->toBe('-3.250')
        ->and($reservation->fresh()->releasedBy->is($actor))->toBeTrue();

    expect(fn () => $reservation->release($actor))->toThrow(DomainException::class)
        ->and(function () use ($movements): void {
            $movement = $movements->first();
            $movement->forceFill(['quantity' => '4.000']);
            $movement->save();
        })->toThrow(LogicException::class)
        ->and(fn () => $movements->first()->delete())->toThrow(LogicException::class);
});

test('stock position excludes expired and quality-held batches from usable stock', function () {
    $material = Material::factory()->create([
        'minimum_stock_qty' => '5.000',
        'reorder_to_qty' => '20.000',
    ]);
    InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-EXPIRED-STOCK',
        'quantity_received' => '4.000',
        'quantity_on_hand' => '4.000',
        'expires_at' => now()->subDay()->toDateString(),
        'quality_status' => InventoryBatchQualityStatus::Available,
    ]);
    InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-USABLE-STOCK',
        'quantity_received' => '2.000',
        'quantity_on_hand' => '2.000',
        'expires_at' => now()->addDays(10)->toDateString(),
        'quality_status' => InventoryBatchQualityStatus::Available,
    ]);
    InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-HELD-STOCK',
        'quantity_received' => '10.000',
        'quantity_on_hand' => '10.000',
        'quality_status' => InventoryBatchQualityStatus::Held,
    ]);
    MaterialStockReservation::factory()->for($material)->create([
        'quantity' => '1.000',
    ]);

    $position = $material->fresh()->load(['inventoryBatches', 'stockReservations'])->stockPosition();

    expect($position['physical_on_hand'])->toBe('16.000')
        ->and($position['usable_on_hand'])->toBe('2.000')
        ->and($position['reserved'])->toBe('1.000')
        ->and($position['available_to_use'])->toBe('1.000')
        ->and($position['low_stock'])->toBeTrue()
        ->and($position['suggested_replenishment'])->toBe('19.000');
});
