<?php

use App\InventoryBatchQualityStatus;
use App\InventoryMovementType;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\PurchaseRequestApprovalDecision;
use Inertia\Testing\AssertableInertia as Assert;

function approvedDistributionRequest(Material $material, string $quantity): PurchaseRequest
{
    $request = PurchaseRequest::factory()->create([
        'request_date' => today()->toDateString(),
        'required_date' => today()->addDays(7)->toDateString(),
    ]);
    PurchaseRequestItem::factory()->for($request)->for($material)->create([
        'unit_of_measure_id' => $material->unit_of_measure_id,
        'requested_qty' => $quantity,
    ]);
    $request->submit();
    $request->recordDecision(
        User::factory()->create(),
        PurchaseRequestApprovalDecision::Approved,
    );

    return $request;
}

test('distribution overview summarizes approved demand against usable stock and supply risk', function () {
    $material = Material::factory()->create([
        'code' => 'MAT-DIST-01',
        'minimum_stock_qty' => '5.000',
    ]);
    InventoryBatch::factory()->for($material)->create([
        'quantity_on_hand' => '2.000',
        'quality_status' => InventoryBatchQualityStatus::Available,
    ]);
    approvedDistributionRequest($material, '8.000');

    $this->actingAs(User::factory()->create(['role' => 'Sales & Distribution Staff']))
        ->get(route('distribution.overview'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Distribution/Overview')
            ->where('summary.approved_quantity', '8.000')
            ->where('summary.usable_stock', '2.000')
            ->where('summary.shortage_materials', 1)
            ->where('materials.0.shortage_quantity', '6.000')
            ->where('materials.0.risk', 'shortage')
            ->where('notices.0.severity', 'high'));
});

test('availability report filters approved demand by approval period and material', function () {
    $material = Material::factory()->create([
        'code' => 'MAT-DIST-FILTER',
        'name' => 'Filter target material',
        'minimum_stock_qty' => '5.000',
    ]);
    InventoryBatch::factory()->for($material)->create([
        'quantity_on_hand' => '14.000',
        'quality_status' => InventoryBatchQualityStatus::Available,
    ]);
    approvedDistributionRequest($material, '6.000');

    $this->actingAs(User::factory()->create(['role' => 'Sales & Distribution Staff']))
        ->get(route('distribution.reports', [
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
            'search' => 'MAT-DIST-FILTER',
            'risk' => 'sufficient',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Distribution/AvailabilityReports')
            ->where('filters.search', 'MAT-DIST-FILTER')
            ->where('summary.approved_quantity', '6.000')
            ->where('summary.usable_stock', '14.000')
            ->where('materials.0.code', 'MAT-DIST-FILTER')
            ->where('materials.0.risk', 'sufficient'));

    $this->actingAs(User::factory()->create(['role' => 'Sales & Distribution Staff']))
        ->get(route('distribution.reports', [
            'from' => today()->subDays(10)->toDateString(),
            'to' => today()->subDays(5)->toDateString(),
            'search' => 'MAT-DIST-FILTER',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Distribution/AvailabilityReports')
            ->where('summary.approved_quantity', '0.000'));
});

test('distribution overview presents daily changes in usable inventory availability', function () {
    $actor = User::factory()->create(['role' => 'Sales & Distribution Staff']);
    $delivery = Delivery::factory()->create();
    $purchaseOrderItem = $delivery->purchaseOrder->items()->firstOrFail();
    $deliveryItem = DeliveryItem::factory()->for($delivery)->create([
        'purchase_order_item_id' => $purchaseOrderItem->getKey(),
        'dispatched_qty' => '10.000',
    ]);
    $delivery->dispatch($actor);
    $receipt = GoodsReceipt::factory()->create([
        'delivery_id' => $delivery->getKey(),
        'purchase_order_id' => $delivery->purchase_order_id,
    ]);
    $receiptItem = GoodsReceiptItem::factory()->for($receipt)->create([
        'delivery_item_id' => $deliveryItem->getKey(),
        'purchase_order_item_id' => $purchaseOrderItem->getKey(),
        'accepted_qty' => '4.000',
        'rejected_qty' => '0.000',
    ]);
    $receipt->post($actor);
    $batch = $receiptItem->fresh()->inventoryBatch;
    InventoryMovement::record(
        InventoryMovementType::StockOut,
        $batch->material,
        '1.500',
        '-1.500',
        '0.000',
        $actor,
        $batch,
    );

    $this->actingAs($actor)
        ->get(route('distribution.overview', [
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Distribution/Overview')
            ->where('trend.0.increased', '4.000')
            ->where('trend.0.decreased', '1.500')
            ->where('trend.0.net_change', '2.500'));
});

test('distribution notifications include overdue deliveries and expired supply', function () {
    $actor = User::factory()->create(['role' => 'Sales & Distribution Staff']);
    $material = Material::factory()->create();
    $batch = InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-DIST-EXPIRED',
        'quantity_on_hand' => '3.000',
        'expires_at' => today()->subDays(2)->toDateString(),
    ]);
    $delivery = Delivery::factory()->dispatched()->create([
        'expected_arrival_date' => today()->subDay()->toDateString(),
    ]);

    $this->actingAs($actor)
        ->get(route('distribution.notifications'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Distribution/Notifications')
            ->where('notices.0.id', 'expiry-'.$batch->getKey())
            ->where('notices.1.id', 'overdue-delivery-'.$delivery->getKey())
            ->where('notices.1.severity', 'high'));
});

test('distribution pages require an authorized role', function () {
    $this->actingAs(User::factory()->create(['role' => 'Warehouse Staff']))
        ->get(route('distribution.overview'))
        ->assertForbidden();
});

test('distribution pages require sign-in', function () {
    $this->get(route('distribution.reports'))->assertRedirect(route('login'));
    $this->get(route('distribution.notifications'))->assertRedirect(route('login'));
});

test('availability reports reject a start date after the end date', function () {
    $this->actingAs(User::factory()->create(['role' => 'Sales & Distribution Staff']))
        ->from(route('distribution.reports'))
        ->get(route('distribution.reports', [
            'from' => today()->toDateString(),
            'to' => today()->subDay()->toDateString(),
        ]))
        ->assertRedirect(route('distribution.reports'))
        ->assertSessionHasErrors('from');
});
