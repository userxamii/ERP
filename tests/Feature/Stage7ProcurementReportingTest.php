<?php

use App\DeliveryStatus;
use App\GoodsReceiptStatus;
use App\InventoryBatchQualityStatus;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryBatch;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\ProcurementDashboardReport;
use App\PurchaseOrderStatus;
use App\PurchaseRequestApprovalDecision;

test('procurement report returns stable empty KPIs without inserting data', function () {
    $report = (new ProcurementDashboardReport)->snapshot(today(), activityLimit: 5);

    expect($report['kpis'])->toBe([
        'low_stock_material_count' => 0,
        'approved_unpurchased_request_item_count' => 0,
        'approved_unpurchased_quantity' => '0.000',
        'open_inbound_quantity' => '0.000',
        'overdue_delivery_count' => 0,
        'expiring_batch_count' => 0,
        'expired_batch_count' => 0,
        'rejected_quantity' => '0.000',
    ])->and($report['recent_activities'])->toBeEmpty();
});

test('procurement report counts low stock outstanding approved demand overdue deliveries expiry and rejected goods', function () {
    $today = today();
    $material = Material::factory()->create([
        'minimum_stock_qty' => '5.000',
        'reorder_to_qty' => '20.000',
    ]);
    InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-EXPIRING-REPORT',
        'quantity_received' => '2.000',
        'quantity_on_hand' => '2.000',
        'expires_at' => $today->copy()->addDays(5)->toDateString(),
        'quality_status' => InventoryBatchQualityStatus::Available,
    ]);
    InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-EXPIRED-REPORT',
        'quantity_received' => '2.000',
        'quantity_on_hand' => '2.000',
        'expires_at' => $today->copy()->subDay()->toDateString(),
        'quality_status' => InventoryBatchQualityStatus::Available,
    ]);

    $purchaseRequest = PurchaseRequest::factory()->create();
    $requestItem = PurchaseRequestItem::factory()->for($purchaseRequest)->for($material)->create([
        'requested_qty' => '8.000',
    ]);
    $purchaseRequest->submit();
    $purchaseRequest->recordDecision(
        User::factory()->create(),
        PurchaseRequestApprovalDecision::Approved,
    );

    $draftOrder = PurchaseOrder::factory()->create();
    $draftOrderItem = PurchaseOrderItem::factory()->for($draftOrder)->for($material)->create([
        'unit_of_measure_id' => $material->unit_of_measure_id,
        'ordered_qty' => '3.000',
    ]);
    $requestItem->allocateTo($draftOrderItem, '3.000');

    $orderedOrder = PurchaseOrder::factory()->create();
    $orderedOrderItem = PurchaseOrderItem::factory()->for($orderedOrder)->for($material)->create([
        'unit_of_measure_id' => $material->unit_of_measure_id,
        'ordered_qty' => '4.000',
    ]);
    $requestItem->allocateTo($orderedOrderItem, '4.000');
    $orderedOrder->place(User::factory()->create());

    $delivery = Delivery::factory()->create([
        'purchase_order_id' => $orderedOrder->getKey(),
        'expected_arrival_date' => $today->copy()->subDay()->toDateString(),
    ]);
    $deliveryItem = DeliveryItem::factory()->for($delivery)->create([
        'purchase_order_item_id' => $orderedOrderItem->getKey(),
        'dispatched_qty' => '3.000',
    ]);
    $delivery->dispatch(User::factory()->create());
    $receipt = GoodsReceipt::factory()->create([
        'delivery_id' => $delivery->getKey(),
        'purchase_order_id' => $orderedOrder->getKey(),
    ]);
    GoodsReceiptItem::factory()->for($receipt)->create([
        'delivery_item_id' => $deliveryItem->getKey(),
        'purchase_order_item_id' => $orderedOrderItem->getKey(),
        'accepted_qty' => '1.000',
        'rejected_qty' => '0.500',
        'discrepancy_notes' => 'One half unit was damaged.',
    ]);
    $receipt->post(User::factory()->create());

    $report = (new ProcurementDashboardReport)->snapshot($today, activityLimit: 5);

    expect($report['kpis']['low_stock_material_count'])->toBe(1)
        ->and($report['kpis']['approved_unpurchased_request_item_count'])->toBe(1)
        ->and($report['kpis']['approved_unpurchased_quantity'])->toBe('4.000')
        ->and($report['kpis']['open_inbound_quantity'])->toBe('1.500')
        ->and($report['kpis']['overdue_delivery_count'])->toBe(1)
        ->and($report['kpis']['expiring_batch_count'])->toBe(1)
        ->and($report['kpis']['expired_batch_count'])->toBe(1)
        ->and($report['kpis']['rejected_quantity'])->toBe('0.500')
        ->and($report['overdue_deliveries']->first()->is($delivery))->toBeTrue()
        ->and($report['expected_deliveries']->first()->status)->toBe(DeliveryStatus::PartiallyReceived)
        ->and($report['recent_activities']->count())->toBeLessThanOrEqual(5)
        ->and($orderedOrder->fresh()->status)->toBe(PurchaseOrderStatus::Ordered)
        ->and($receipt->fresh()->status)->toBe(GoodsReceiptStatus::Posted);
});
