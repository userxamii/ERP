<?php

use App\DeliveryStatus;
use App\GoodsReceiptStatus;
use App\Http\Requests\Procurement\StoreDeliveryItemRequest;
use App\Http\Requests\Procurement\StoreDeliveryRequest;
use App\Http\Requests\Procurement\StoreGoodsReceiptItemRequest;
use App\Http\Requests\Procurement\StoreGoodsReceiptRequest;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryBatch;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

test('delivery dispatch accepts valid ordered quantities and records the dispatched workflow', function () {
    $delivery = Delivery::factory()->create();
    $orderItem = $delivery->purchaseOrder->items()->firstOrFail();
    $deliveryItem = DeliveryItem::factory()->for($delivery)->create([
        'purchase_order_item_id' => $orderItem->getKey(),
        'dispatched_qty' => '6.000',
    ]);
    $actor = User::factory()->create();

    $delivery->dispatch($actor);

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Dispatched)
        ->and($delivery->fresh()->dispatched_at)->not->toBeNull()
        ->and($deliveryItem->purchaseOrderItem->is($orderItem))->toBeTrue()
        ->and($delivery->procurementActivities()->where('activity_type', 'delivery_dispatched')->exists())->toBeTrue();
});

test('partial dispatches across deliveries cannot exceed the ordered quantity', function () {
    $firstDelivery = Delivery::factory()->create();
    $purchaseOrder = $firstDelivery->purchaseOrder;
    $orderItem = $purchaseOrder->items()->firstOrFail();
    DeliveryItem::factory()->for($firstDelivery)->create([
        'purchase_order_item_id' => $orderItem->getKey(),
        'dispatched_qty' => '6.000',
    ]);
    $firstDelivery->dispatch(User::factory()->create());

    $secondDelivery = Delivery::factory()->create([
        'purchase_order_id' => $purchaseOrder->getKey(),
    ]);
    DeliveryItem::factory()->for($secondDelivery)->create([
        'purchase_order_item_id' => $orderItem->getKey(),
        'dispatched_qty' => '5.000',
    ]);

    expect(fn () => $secondDelivery->dispatch(User::factory()->create()))
        ->toThrow(DomainException::class);

    $secondDeliveryItem = $secondDelivery->items()->firstOrFail();
    $secondDeliveryItem->update(['dispatched_qty' => '4.000']);
    $secondDelivery->dispatch(User::factory()->create());

    expect($secondDelivery->fresh()->status)->toBe(DeliveryStatus::Dispatched)
        ->and($orderItem->fresh()->dispatchedQuantityMilli())->toBe(10000);
});

test('goods receipts support partial acceptance and rejection without adding rejected stock', function () {
    $delivery = Delivery::factory()->create();
    $orderItem = $delivery->purchaseOrder->items()->firstOrFail();
    $deliveryItem = DeliveryItem::factory()->for($delivery)->create([
        'purchase_order_item_id' => $orderItem->getKey(),
        'dispatched_qty' => '10.000',
    ]);
    $delivery->dispatch(User::factory()->create());
    $receipt = GoodsReceipt::factory()->create([
        'delivery_id' => $delivery->getKey(),
        'purchase_order_id' => $delivery->purchase_order_id,
    ]);
    $receiptItem = GoodsReceiptItem::factory()->for($receipt)->create([
        'delivery_item_id' => $deliveryItem->getKey(),
        'purchase_order_item_id' => $orderItem->getKey(),
        'accepted_qty' => '7.000',
        'rejected_qty' => '1.000',
        'discrepancy_notes' => 'One unit arrived damaged.',
        'lot_code' => null,
        'expires_at' => '2027-10-11',
    ]);
    $actor = User::factory()->create();

    $receipt->post($actor, new DateTimeImmutable('2026-10-11 09:00:00'));

    expect($receipt->fresh()->status)->toBe(GoodsReceiptStatus::Posted)
        ->and($delivery->fresh()->status)->toBe(DeliveryStatus::PartiallyReceived)
        ->and($receiptItem->inventoryBatch)->toBeInstanceOf(InventoryBatch::class)
        ->and($receiptItem->inventoryBatch->quantity_on_hand)->toBe('7.000')
        ->and($orderItem->fresh()->receivedQuantityMilli())->toBe(8000);
});

test('posted receipt retries are idempotent and posted records cannot be edited', function () {
    $delivery = Delivery::factory()->create();
    $orderItem = $delivery->purchaseOrder->items()->firstOrFail();
    $deliveryItem = DeliveryItem::factory()->for($delivery)->create([
        'purchase_order_item_id' => $orderItem->getKey(),
        'dispatched_qty' => '10.000',
    ]);
    $delivery->dispatch(User::factory()->create());
    $receipt = GoodsReceipt::factory()->create([
        'delivery_id' => $delivery->getKey(),
        'purchase_order_id' => $delivery->purchase_order_id,
    ]);
    GoodsReceiptItem::factory()->for($receipt)->create([
        'delivery_item_id' => $deliveryItem->getKey(),
        'purchase_order_item_id' => $orderItem->getKey(),
        'accepted_qty' => '2.000',
    ]);

    $receipt->post(User::factory()->create());
    $movementCount = $receipt->items()->firstOrFail()->inventoryMovements()->count();
    $receipt->post(User::factory()->create());

    expect($receipt->items()->firstOrFail()->inventoryMovements()->count())->toBe($movementCount)
        ->and($receipt->items()->firstOrFail()->inventoryBatch()->count())->toBe(1)
        ->and(fn () => $receipt->update(['notes' => 'Changed after posting']))
        ->toThrow(DomainException::class)
        ->and(fn () => $receipt->items()->firstOrFail()->update(['accepted_qty' => '3.000']))
        ->toThrow(DomainException::class);
});

test('delivery and receipt constraints and form requests reject invalid or client-controlled data', function () {
    $delivery = Delivery::factory()->create();
    $orderItem = $delivery->purchaseOrder->items()->firstOrFail();

    DeliveryItem::factory()->for($delivery)->create([
        'purchase_order_item_id' => $orderItem->getKey(),
    ]);

    expect(fn () => DeliveryItem::factory()->for($delivery)->create([
        'purchase_order_item_id' => $orderItem->getKey(),
    ]))->toThrow(QueryException::class);

    $deliveryRequestData = [
        'delivery_number' => 'DEL-CLIENT',
        'purchase_order_id' => $delivery->purchase_order_id,
        'supplier_id' => $delivery->supplier_id,
        'status' => 'received',
        'created_by_user_id' => User::factory()->create()->id,
    ];
    $deliveryValidator = Validator::make($deliveryRequestData, (new StoreDeliveryRequest)->rules());

    $deliveryItemData = [
        'delivery_id' => $delivery->getKey(),
        'purchase_order_item_id' => $orderItem->getKey(),
        'dispatched_qty' => '0.000',
    ];
    $deliveryItemValidator = Validator::make(
        $deliveryItemData,
        (new StoreDeliveryItemRequest)->rules(),
    );

    $receiptData = [
        'receipt_number' => 'GR-CLIENT',
        'delivery_id' => $delivery->getKey(),
        'status' => 'posted',
        'received_by_user_id' => User::factory()->create()->id,
    ];
    $receiptValidator = Validator::make($receiptData, (new StoreGoodsReceiptRequest)->rules());

    $receivingDelivery = Delivery::factory()->create();
    $receivingOrderItem = $receivingDelivery->purchaseOrder->items()->firstOrFail();
    $receivingDeliveryItem = DeliveryItem::factory()->for($receivingDelivery)->create([
        'purchase_order_item_id' => $receivingOrderItem->getKey(),
        'dispatched_qty' => '10.000',
    ]);
    $receivingDelivery->dispatch(User::factory()->create());
    $receipt = GoodsReceipt::factory()->create([
        'delivery_id' => $receivingDelivery->getKey(),
        'purchase_order_id' => $receivingDelivery->purchase_order_id,
    ]);
    $receiptItemData = [
        'goods_receipt_id' => $receipt->getKey(),
        'delivery_item_id' => $receivingDeliveryItem->getKey(),
        'purchase_order_item_id' => $receivingOrderItem->getKey(),
        'accepted_qty' => '0.000',
        'rejected_qty' => '1.000',
    ];
    $receiptItemRequest = StoreGoodsReceiptItemRequest::create('/', 'POST', $receiptItemData);
    $receiptItemValidator = Validator::make(
        $receiptItemData,
        $receiptItemRequest->rules(),
    );
    $receiptItemValidator->after(...$receiptItemRequest->after());

    expect($deliveryValidator->fails())->toBeTrue()
        ->and($deliveryValidator->errors()->has('supplier_id'))->toBeTrue()
        ->and($deliveryValidator->errors()->has('status'))->toBeTrue()
        ->and($deliveryValidator->errors()->has('created_by_user_id'))->toBeTrue()
        ->and($deliveryItemValidator->fails())->toBeTrue()
        ->and($deliveryItemValidator->errors()->has('dispatched_qty'))->toBeTrue()
        ->and($receiptValidator->fails())->toBeTrue()
        ->and($receiptValidator->errors()->has('status'))->toBeTrue()
        ->and($receiptValidator->errors()->has('received_by_user_id'))->toBeTrue()
        ->and($receiptItemValidator->fails())->toBeTrue()
        ->and($receiptItemValidator->errors()->has('discrepancy_notes'))->toBeTrue();
});
