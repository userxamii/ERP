<?php

use App\InventoryMovementType;
use App\Models\Delivery;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Models\User;
use App\PurchaseRequestApprovalDecision;
use App\PurchaseRequestStatus;
use Database\Seeders\ProcurementProductSeeder;

test('the procurement product seeder makes Product A through J selectable', function () {
    $this->seed(ProcurementProductSeeder::class);

    $staff = User::factory()->create(['role' => 'Procurement Staff']);

    $this->actingAs($staff)
        ->getJson(route('warehouse.procurement.materials'))
        ->assertOk()
        ->assertJsonCount(10, 'materials')
        ->assertJsonPath('materials.0.code', 'PROD-A')
        ->assertJsonPath('materials.0.name', 'Product A')
        ->assertJsonPath('materials.9.code', 'PROD-J')
        ->assertJsonPath('materials.9.name', 'Product J');

    $this->actingAs($staff)
        ->getJson(route('warehouse.procurement.requests'))
        ->assertOk()
        ->assertJsonCount(10, 'materials')
        ->assertJsonPath('materials.0.name', 'Product A')
        ->assertJsonPath('materials.9.name', 'Product J');
});

test('procurement staff can view real material requirements and create requests as drafts', function () {
    $staff = User::factory()->create(['role' => 'Procurement Staff']);
    $material = Material::factory()->create([
        'code' => 'MAT-LOW-001',
        'name' => 'Low stock material',
        'minimum_stock_qty' => '5.000',
        'reorder_to_qty' => '20.000',
    ]);

    $this->actingAs($staff)
        ->getJson(route('warehouse.procurement.materials'))
        ->assertOk()
        ->assertJsonPath('materials.0.code', 'MAT-LOW-001')
        ->assertJsonPath('materials.0.available', '0.000')
        ->assertJsonPath('materials.0.minimum', '5.000')
        ->assertJsonPath('materials.0.suggestedReplenishment', '20.000')
        ->assertJsonPath('materials.0.risk', 'out_of_stock');

    $this->actingAs($staff)
        ->postJson(route('warehouse.procurement.requests.store'), [
            'request_number' => 'PR-WORKSPACE-001',
            'request_date' => today()->toDateString(),
            'required_date' => today()->addDays(7)->toDateString(),
            'purpose' => 'Replenish low stock',
            'material_id' => $material->getKey(),
            'requested_qty' => '12.000',
            'justification' => 'Below the minimum level.',
        ])
        ->assertCreated()
        ->assertJsonPath('request.number', 'PR-WORKSPACE-001')
        ->assertJsonPath('request.status', PurchaseRequestStatus::Draft->value)
        ->assertJsonPath('request.items.0.quantity', '12.000')
        ->assertJsonPath('message', 'Purchase request saved as a draft. Submit it for approval when ready.');

    $purchaseRequest = PurchaseRequest::query()->where('request_number', 'PR-WORKSPACE-001')->firstOrFail();
    $this->assertDatabaseHas('purchase_requests', [
        'id' => $purchaseRequest->getKey(),
        'requested_by_user_id' => $staff->getKey(),
        'status' => PurchaseRequestStatus::Draft->value,
    ]);
    $this->assertDatabaseHas('purchase_request_items', [
        'purchase_request_id' => $purchaseRequest->getKey(),
        'material_id' => $material->getKey(),
        'requested_qty' => '12.000',
    ]);

    $this->actingAs($staff)
        ->postJson(route('warehouse.procurement.requests.submit', $purchaseRequest))
        ->assertOk()
        ->assertJsonPath('request.status', PurchaseRequestStatus::Submitted->value);
});

test('material requirements estimate recent daily use and uncovered approved demand', function () {
    $staff = User::factory()->create(['role' => 'Procurement Staff']);
    $material = Material::factory()->create([
        'code' => 'MAT-DEMAND-001',
        'minimum_stock_qty' => '5.000',
        'reorder_to_qty' => '20.000',
    ]);
    $batch = InventoryBatch::factory()->for($material)->create([
        'quantity_received' => '5.000',
        'quantity_on_hand' => '5.000',
    ]);
    InventoryMovement::record(
        InventoryMovementType::StockOut,
        $material,
        '1.000',
        '-1.000',
        '0.000',
        $staff,
        $batch,
    );
    $purchaseRequest = PurchaseRequest::factory()->create();
    PurchaseRequestItem::factory()->for($purchaseRequest)->for($material)->create([
        'requested_qty' => '50.000',
    ]);
    $purchaseRequest->submit();
    $purchaseRequest->recordDecision(
        User::factory()->create(['role' => 'Management']),
        PurchaseRequestApprovalDecision::Approved,
    );

    $this->actingAs($staff)
        ->getJson(route('warehouse.procurement.materials'))
        ->assertOk()
        ->assertJsonPath('materials.0.dailyUsage', '0.033')
        ->assertJsonPath('materials.0.approvedDemand', '50.000')
        ->assertJsonPath('materials.0.shortageQuantity', '45.000')
        ->assertJsonPath('materials.0.suggestedReplenishment', '45.000')
        ->assertJsonPath('materials.0.risk', 'shortage');
});

test('procurement staff cannot approve purchase requests but management can', function () {
    $purchaseRequest = PurchaseRequest::factory()->create();
    PurchaseRequestItem::factory()->for($purchaseRequest)->create();
    $purchaseRequest->submit();
    $staff = User::factory()->create(['role' => 'Procurement Staff']);
    $management = User::factory()->create(['role' => 'Management']);

    $this->actingAs($staff)
        ->postJson(route('warehouse.procurement.requests.decision', $purchaseRequest), [
            'purchase_request_id' => $purchaseRequest->getKey(),
            'decision' => PurchaseRequestApprovalDecision::Approved->value,
        ])
        ->assertForbidden();

    $this->actingAs($management)
        ->postJson(route('warehouse.procurement.requests.decision', $purchaseRequest), [
            'purchase_request_id' => $purchaseRequest->getKey(),
            'decision' => PurchaseRequestApprovalDecision::Approved->value,
        ])
        ->assertOk()
        ->assertJsonPath('request.status', PurchaseRequestStatus::Approved->value);

    $this->assertDatabaseHas('purchase_request_approvals', [
        'purchase_request_id' => $purchaseRequest->getKey(),
        'approver_user_id' => $management->getKey(),
        'decision' => PurchaseRequestApprovalDecision::Approved->value,
    ]);
});

test('procurement staff can create purchase orders only from approved request demand', function () {
    $staff = User::factory()->create(['role' => 'Procurement Staff']);
    $supplier = Supplier::factory()->create();
    $purchaseRequest = PurchaseRequest::factory()->create();
    $requestItem = PurchaseRequestItem::factory()->for($purchaseRequest)->create([
        'requested_qty' => '8.000',
    ]);
    $purchaseRequest->submit();
    $purchaseRequest->recordDecision(
        User::factory()->create(['role' => 'Management']),
        PurchaseRequestApprovalDecision::Approved,
    );

    $this->actingAs($staff)
        ->postJson(route('warehouse.procurement.orders.store'), [
            'order_number' => 'PO-WORKSPACE-001',
            'supplier_id' => $supplier->getKey(),
            'order_date' => today()->toDateString(),
            'expected_delivery_date' => today()->addDays(14)->toDateString(),
            'currency_code' => 'PHP',
            'items' => [[
                'purchase_request_item_id' => $requestItem->getKey(),
                'ordered_qty' => '8.000',
                'unit_price' => '4.2500',
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('order.number', 'PO-WORKSPACE-001')
        ->assertJsonPath('order.status', 'draft')
        ->assertJsonPath('order.items.0.quantity', '8.000');

    $purchaseOrder = PurchaseOrder::query()->where('order_number', 'PO-WORKSPACE-001')->firstOrFail();
    $this->assertDatabaseHas('purchase_order_item_allocations', [
        'purchase_request_item_id' => $requestItem->getKey(),
        'allocated_qty' => '8.000',
    ]);

    $this->actingAs($staff)
        ->postJson(route('warehouse.procurement.orders.place', $purchaseOrder))
        ->assertOk()
        ->assertJsonPath('order.status', 'ordered');
});

test('procurement staff can create suppliers and post goods receipts with lot traceability', function () {
    $staff = User::factory()->create(['role' => 'Procurement Staff']);

    $this->actingAs($staff)
        ->postJson(route('warehouse.procurement.suppliers.store'), [
            'supplier_code' => 'SUP-WORKSPACE-001',
            'name' => 'Workspace Supplier',
            'contact_email' => 'supplier@example.com',
            'is_active' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('supplier.code', 'SUP-WORKSPACE-001')
        ->assertJsonPath('supplier.active', true);

    $purchaseOrder = PurchaseOrder::factory()->ordered()->create();
    $purchaseOrderItem = $purchaseOrder->items()->firstOrFail();

    $this->actingAs($staff)
        ->postJson(route('warehouse.procurement.deliveries.store'), [
            'delivery_number' => 'DEL-WORKSPACE-001',
            'purchase_order_id' => $purchaseOrder->getKey(),
            'dispatch_date' => today()->toDateString(),
            'expected_arrival_date' => today()->toDateString(),
            'items' => [[
                'purchase_order_item_id' => $purchaseOrderItem->getKey(),
                'dispatched_qty' => '3.000',
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('delivery.number', 'DEL-WORKSPACE-001')
        ->assertJsonPath('delivery.status', 'dispatched');

    $delivery = Delivery::query()->where('delivery_number', 'DEL-WORKSPACE-001')->firstOrFail();
    $deliveryItem = $delivery->items()->firstOrFail();

    $this->actingAs($staff)
        ->postJson(route('warehouse.procurement.deliveries.receipts.store', $delivery), [
            'receipt_number' => 'GR-WORKSPACE-001',
            'delivery_id' => $delivery->getKey(),
            'actual_received_at' => now()->toDateTimeString(),
            'items' => [[
                'delivery_item_id' => $deliveryItem->getKey(),
                'accepted_qty' => '2.000',
                'rejected_qty' => '1.000',
                'lot_code' => 'LOT-WORKSPACE-001',
                'discrepancy_notes' => 'One unit was damaged.',
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('receipt.number', 'GR-WORKSPACE-001')
        ->assertJsonPath('receipt.status', 'posted');

    $this->assertDatabaseHas('inventory_batches', [
        'lot_code' => 'LOT-WORKSPACE-001',
        'quantity_received' => '2.000',
        'received_by_user_id' => $staff->getKey(),
    ]);
});

test('unauthenticated and unrelated roles cannot access the procurement workspace', function () {
    $this->getJson(route('warehouse.procurement.dashboard'))
        ->assertUnauthorized();

    $warehouseStaff = User::factory()->create(['role' => 'Warehouse Staff']);

    $this->actingAs($warehouseStaff)
        ->getJson(route('warehouse.procurement.dashboard'))
        ->assertForbidden();
});
