<?php

use App\InventoryBatchQualityStatus;
use App\InventoryMovementType;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\MaterialStockReservation;
use App\Models\ProductionMaterialRequest;
use App\Models\User;
use App\ProductionMaterialRequestStatus;

test('production staff can view usable materials and the FEFO queue excludes QA-held and expired batches', function () {
    $staff = User::factory()->create(['role' => 'Production Staff']);
    $material = Material::factory()->create(['code' => 'MAT-PROD-FEFO']);
    InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-HELD-EARLY',
        'expires_at' => today()->addDay(),
        'quality_status' => InventoryBatchQualityStatus::Held,
        'quantity_on_hand' => '5.000',
    ]);
    InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-EXPIRED',
        'expires_at' => today()->subDay(),
        'quantity_on_hand' => '4.000',
    ]);
    InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-FEFO-SECOND',
        'expires_at' => today()->addDays(8),
        'quantity_on_hand' => '3.000',
        'storage_location' => 'Rack B',
    ]);
    InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-FEFO-FIRST',
        'expires_at' => today()->addDays(3),
        'quantity_on_hand' => '2.000',
        'storage_location' => 'Rack A',
    ]);

    $this->actingAs($staff)
        ->getJson(route('warehouse.production.materials'))
        ->assertOk()
        ->assertJsonPath('materials.0.code', 'MAT-PROD-FEFO')
        ->assertJsonPath('materials.0.availableQuantity', '5.000')
        ->assertJsonPath('batches.0.lotCode', 'LOT-EXPIRED')
        ->assertJsonPath('batches.0.eligible', false)
        ->assertJsonPath('batches.1.lotCode', 'LOT-HELD-EARLY')
        ->assertJsonPath('batches.1.eligible', false)
        ->assertJsonPath('batches.2.lotCode', 'LOT-FEFO-FIRST')
        ->assertJsonPath('batches.2.recommended', true)
        ->assertJsonPath('batches.3.recommended', false);
});

test('production requests require approval and warehouse fulfillment records stock-out movements', function () {
    $productionStaff = User::factory()->create(['role' => 'Production Staff']);
    $manager = User::factory()->create(['role' => 'Management']);
    $warehouseStaff = User::factory()->create(['role' => 'Warehouse Staff']);
    $material = Material::factory()->create();
    $batch = InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-PROD-ISSUE',
        'quantity_received' => '10.000',
        'quantity_on_hand' => '10.000',
        'storage_location' => 'Rack 4',
    ]);

    $this->actingAs($productionStaff)
        ->postJson(route('warehouse.production.requests.store'), [
            'request_number' => 'MR-PROD-001',
            'material_id' => $material->getKey(),
            'requested_qty' => '5.000',
            'production_order' => 'PO-LINE-22',
            'purpose' => 'Prepare the scheduled production run.',
            'required_date' => today()->addDays(2)->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('request.status', ProductionMaterialRequestStatus::PendingApproval->value);

    $materialRequest = ProductionMaterialRequest::query()->where('request_number', 'MR-PROD-001')->firstOrFail();

    $this->actingAs($manager)
        ->postJson(route('warehouse.production.requests.decision', $materialRequest), [
            'decision' => 'approve',
            'comments' => 'Approved for scheduled production.',
        ])
        ->assertOk()
        ->assertJsonPath('request.status', ProductionMaterialRequestStatus::Approved->value)
        ->assertJsonPath('request.approver', $manager->name);

    $this->actingAs($warehouseStaff)
        ->postJson(route('warehouse.production.requests.fulfill', $materialRequest), [
            'inventory_batch_id' => $batch->getKey(),
            'quantity' => '2.000',
            'destination_location' => 'Production Line 2',
        ])
        ->assertOk()
        ->assertJsonPath('request.status', ProductionMaterialRequestStatus::PartiallyFulfilled->value)
        ->assertJsonPath('request.fulfilledQuantity', '2.000')
        ->assertJsonPath('request.remainingQuantity', '3.000');

    $this->actingAs($warehouseStaff)
        ->postJson(route('warehouse.production.requests.fulfill', $materialRequest), [
            'inventory_batch_id' => $batch->getKey(),
            'quantity' => '3.000',
            'destination_location' => 'Production Line 2',
        ])
        ->assertOk()
        ->assertJsonPath('request.status', ProductionMaterialRequestStatus::Fulfilled->value)
        ->assertJsonPath('request.fulfilledQuantity', '5.000')
        ->assertJsonPath('request.remainingQuantity', '0.000');

    expect($batch->fresh()->quantity_on_hand)->toBe('5.000');
    $this->assertDatabaseHas('inventory_movements', [
        'inventory_batch_id' => $batch->getKey(),
        'movement_type' => InventoryMovementType::StockOut->value,
        'source_reference' => 'MR-PROD-001',
        'destination_location' => 'Production Line 2',
        'reason' => 'Approved production material request',
        'actor_user_id' => $warehouseStaff->getKey(),
    ]);
    expect(InventoryMovement::query()->where('source_reference', 'MR-PROD-001')->count())->toBe(2);
});

test('only Management or System Administrators can approve and only Warehouse Staff can fulfill', function () {
    $productionStaff = User::factory()->create(['role' => 'Production Staff']);
    $warehouseStaff = User::factory()->create(['role' => 'Warehouse Staff']);
    $material = Material::factory()->create();
    $materialRequest = ProductionMaterialRequest::query()->create([
        'request_number' => 'MR-PROD-AUTH',
        'material_id' => $material->getKey(),
        'requested_by_user_id' => $productionStaff->getKey(),
        'requested_qty' => '1.000',
        'purpose' => 'Authorization boundary test.',
    ]);

    $this->actingAs($productionStaff)
        ->postJson(route('warehouse.production.requests.decision', $materialRequest), [
            'decision' => 'approve',
        ])
        ->assertForbidden();

    $this->actingAs($productionStaff)
        ->getJson(route('warehouse.production.requests'))
        ->assertOk()
        ->assertJsonCount(1, 'requests');

    $this->actingAs($warehouseStaff)
        ->postJson(route('warehouse.production.requests.decision', $materialRequest), [
            'decision' => 'approve',
        ])
        ->assertForbidden();

    $this->actingAs($productionStaff)
        ->getJson(route('warehouse.production.materials'))
        ->assertOk();
});

test('system administrators can approve production material requests', function () {
    $productionStaff = User::factory()->create(['role' => 'Production Staff']);
    $administrator = User::factory()->create(['role' => 'System Administrator']);
    $material = Material::factory()->create();
    $materialRequest = ProductionMaterialRequest::query()->create([
        'request_number' => 'MR-PROD-ADMIN',
        'material_id' => $material->getKey(),
        'requested_by_user_id' => $productionStaff->getKey(),
        'requested_qty' => '1.000',
        'purpose' => 'Administrator approval test.',
    ]);

    $this->actingAs($administrator)
        ->postJson(route('warehouse.production.requests.decision', $materialRequest), [
            'decision' => 'approve',
        ])
        ->assertOk()
        ->assertJsonPath('request.status', ProductionMaterialRequestStatus::Approved->value)
        ->assertJsonPath('request.approver', $administrator->name);
});

test('production staff only see their own requests and warehouse cannot issue QA-held batches', function () {
    $requester = User::factory()->create(['role' => 'Production Staff']);
    $otherRequester = User::factory()->create(['role' => 'Production Staff']);
    $warehouseStaff = User::factory()->create(['role' => 'Warehouse Staff']);
    $approver = User::factory()->create(['role' => 'Management']);
    $material = Material::factory()->create();
    $ownRequest = ProductionMaterialRequest::query()->create([
        'request_number' => 'MR-PROD-OWN',
        'material_id' => $material->getKey(),
        'requested_by_user_id' => $requester->getKey(),
        'requested_qty' => '3.000',
        'purpose' => 'Own request.',
    ]);
    ProductionMaterialRequest::query()->create([
        'request_number' => 'MR-PROD-OTHER',
        'material_id' => $material->getKey(),
        'requested_by_user_id' => $otherRequester->getKey(),
        'requested_qty' => '3.000',
        'purpose' => 'Another user request.',
    ]);
    $heldBatch = InventoryBatch::factory()->for($material)->create([
        'quality_status' => InventoryBatchQualityStatus::Held,
    ]);
    $ownRequest->recordDecision($approver, true);

    $this->actingAs($requester)
        ->getJson(route('warehouse.production.requests'))
        ->assertOk()
        ->assertJsonCount(1, 'requests')
        ->assertJsonPath('requests.0.requestNumber', 'MR-PROD-OWN');

    $this->actingAs($warehouseStaff)
        ->postJson(route('warehouse.production.requests.fulfill', $ownRequest), [
            'inventory_batch_id' => $heldBatch->getKey(),
            'quantity' => '1.000',
            'destination_location' => 'Production Line 1',
        ])
        ->assertInvalid('quantity');

    expect($heldBatch->fresh()->quantity_on_hand)->toBe('100.000');
    expect(InventoryMovement::query()->count())->toBe(0);
});

test('warehouse cannot issue reserved stock to fulfill a production request', function () {
    $productionStaff = User::factory()->create(['role' => 'Production Staff']);
    $warehouseStaff = User::factory()->create(['role' => 'Warehouse Staff']);
    $approver = User::factory()->create(['role' => 'Management']);
    $material = Material::factory()->create();
    $batch = InventoryBatch::factory()->for($material)->create([
        'quantity_received' => '10.000',
        'quantity_on_hand' => '10.000',
    ]);
    MaterialStockReservation::factory()->for($material)->create([
        'quantity' => '8.000',
        'created_by_user_id' => $productionStaff->getKey(),
    ]);
    $materialRequest = ProductionMaterialRequest::query()->create([
        'request_number' => 'MR-PROD-RESERVED',
        'material_id' => $material->getKey(),
        'requested_by_user_id' => $productionStaff->getKey(),
        'requested_qty' => '3.000',
        'purpose' => 'Reserved stock must remain unavailable.',
    ]);
    $materialRequest->recordDecision($approver, true);

    $this->actingAs($warehouseStaff)
        ->postJson(route('warehouse.production.requests.fulfill', $materialRequest), [
            'inventory_batch_id' => $batch->getKey(),
            'quantity' => '3.000',
            'destination_location' => 'Production Line 1',
        ])
        ->assertInvalid('quantity');

    expect($batch->fresh()->quantity_on_hand)->toBe('10.000');
    expect(InventoryMovement::query()->where('movement_type', InventoryMovementType::StockOut->value)->count())->toBe(0);
});

test('production workspace screens are served as a separate module', function () {
    $this->get('/product-monitor/screens/production-staff.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');

    expect(file_get_contents(base_path('product-monitor/index.html')))
        ->toContain('/product-monitor/screens/production-staff.js')
        ->toContain('data-page="production-materials"')
        ->toContain('data-page="production-requests"')
        ->toContain('data-page="production-issued"')
        ->toContain('data-page="production-batches"');
});
