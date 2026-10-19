<?php

use App\InventoryBatchQualityStatus;
use App\InventoryMovementType;
use App\Models\InventoryBatch;
use App\Models\Material;
use App\Models\Supplier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('warehouse receipt stores lot traceability and increases inventory', function () {
    $actor = User::factory()->create(['role' => 'Warehouse Staff']);
    $material = Material::factory()->create();
    $supplier = Supplier::factory()->create();

    $response = $this->actingAs($actor)->post(route('warehouse.receipts.store'), [
        'material_id' => $material->getKey(),
        'supplier_id' => $supplier->getKey(),
        'lot_code' => 'LOT-WH-RECEIPT',
        'quantity_received' => '12.500',
        'received_at' => today()->toDateString(),
        'manufactured_at' => today()->subDays(10)->toDateString(),
        'expires_at' => today()->addDays(120)->toDateString(),
        'storage_location' => 'Aisle 2 / Rack 4',
        'reference' => 'DO-2026-104',
    ]);

    $response->assertRedirect(route('warehouse.materials.inventory'));
    $this->assertDatabaseHas('inventory_batches', [
        'material_id' => $material->getKey(),
        'supplier_id' => $supplier->getKey(),
        'received_by_user_id' => $actor->getKey(),
        'lot_code' => 'LOT-WH-RECEIPT',
        'quantity_received' => '12.500',
        'quantity_on_hand' => '12.500',
        'storage_location' => 'Aisle 2 / Rack 4',
        'quality_status' => InventoryBatchQualityStatus::Held->value,
    ]);
    $batch = InventoryBatch::query()->where('lot_code', 'LOT-WH-RECEIPT')->firstOrFail();
    expect($batch->manufactured_at->toDateString())->toBe(today()->subDays(10)->toDateString())
        ->and($batch->expires_at->toDateString())->toBe(today()->addDays(120)->toDateString());
    $this->assertDatabaseHas('inventory_movements', [
        'movement_type' => InventoryMovementType::WarehouseReceipt->value,
        'on_hand_delta' => '12.500',
        'source_reference' => 'DO-2026-104',
        'source_location' => $supplier->name,
        'destination_location' => 'Aisle 2 / Rack 4',
        'actor_user_id' => $actor->getKey(),
    ]);

    $this->actingAs($actor)
        ->get(route('warehouse.materials.inventory', ['search' => $material->code]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('inventory.data.0.available_quantity', '0.000')
            ->where('inventory.data.0.physical_on_hand', '12.500')
            ->where('inventory.data.0.stock_status', 'review_required'));
});

test('warehouse receipt rejects a duplicate lot for the same material', function () {
    $material = Material::factory()->create();
    $supplier = Supplier::factory()->create();
    InventoryBatch::factory()->for($material)->create(['lot_code' => 'LOT-DUPLICATE']);

    $this->actingAs(User::factory()->create())
        ->from(route('warehouse.receipts.create'))
        ->post(route('warehouse.receipts.store'), [
            'material_id' => $material->getKey(),
            'supplier_id' => $supplier->getKey(),
            'lot_code' => 'LOT-DUPLICATE',
            'quantity_received' => '2.000',
            'received_at' => today()->toDateString(),
            'storage_location' => 'Rack 1',
        ])
        ->assertRedirect(route('warehouse.receipts.create'))
        ->assertSessionHasErrors('lot_code');

    $this->assertDatabaseCount('inventory_batches', 1);
    $this->assertDatabaseCount('inventory_movements', 0);
});

test('warehouse receipt accepts materials without manufacture or expiration dates', function () {
    $material = Material::factory()->create();
    $supplier = Supplier::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('warehouse.receipts.store'), [
            'material_id' => $material->getKey(),
            'supplier_id' => $supplier->getKey(),
            'lot_code' => 'LOT-NO-DATE',
            'quantity_received' => '1.000',
            'received_at' => today()->toDateString(),
            'storage_location' => 'Rack 9',
        ])
        ->assertRedirect(route('warehouse.materials.inventory'));

    $batch = InventoryBatch::query()->where('lot_code', 'LOT-NO-DATE')->firstOrFail();
    expect($batch->manufactured_at)->toBeNull()
        ->and($batch->expires_at)->toBeNull()
        ->and($batch->quality_status)->toBe(InventoryBatchQualityStatus::Held);
});

test('stock-out atomically deducts a batch and records the movement details', function () {
    $actor = User::factory()->create(['role' => 'Warehouse Staff']);
    $batch = InventoryBatch::factory()->create([
        'lot_code' => 'LOT-ISSUE',
        'quantity_received' => '10.000',
        'quantity_on_hand' => '10.000',
        'storage_location' => 'Rack 3 / Bin 2',
    ]);

    $response = $this->actingAs($actor)->post(route('warehouse.stock-outs.store'), [
        'inventory_batch_id' => $batch->getKey(),
        'quantity' => '3.250',
        'destination_location' => 'Production Line 1',
        'reference' => 'MR-0008',
        'reason' => 'Production issue',
    ]);

    $response->assertRedirect(route('warehouse.movements'));
    expect($batch->fresh()->quantity_on_hand)->toBe('6.750');
    $this->assertDatabaseHas('inventory_movements', [
        'inventory_batch_id' => $batch->getKey(),
        'movement_type' => InventoryMovementType::StockOut->value,
        'quantity' => '3.250',
        'on_hand_delta' => '-3.250',
        'source_location' => 'Rack 3 / Bin 2',
        'destination_location' => 'Production Line 1',
        'source_reference' => 'MR-0008',
        'reason' => 'Production issue',
        'actor_user_id' => $actor->getKey(),
    ]);
});

test('stock-out rejects quantities above available batch stock without changing inventory', function () {
    $batch = InventoryBatch::factory()->create([
        'quantity_on_hand' => '4.000',
    ]);

    $this->actingAs(User::factory()->create())
        ->from(route('warehouse.movements'))
        ->post(route('warehouse.stock-outs.store'), [
            'inventory_batch_id' => $batch->getKey(),
            'quantity' => '4.001',
            'destination_location' => 'Production Line 1',
            'reason' => 'Production issue',
        ])
        ->assertRedirect(route('warehouse.movements'))
        ->assertSessionHasErrors('quantity');

    expect($batch->fresh()->quantity_on_hand)->toBe('4.000');
    $this->assertDatabaseCount('inventory_movements', 0);
});

test('stock-out cannot issue batches waiting for independent quality review', function () {
    $batch = InventoryBatch::factory()->create([
        'quality_status' => InventoryBatchQualityStatus::Held,
    ]);

    $this->actingAs(User::factory()->create())
        ->from(route('warehouse.movements'))
        ->post(route('warehouse.stock-outs.store'), [
            'inventory_batch_id' => $batch->getKey(),
            'quantity' => '1.000',
            'destination_location' => 'Production Line 1',
            'reason' => 'Production issue',
        ])
        ->assertRedirect(route('warehouse.movements'))
        ->assertSessionHasErrors('inventory_batch_id');

    expect($batch->fresh()->quantity_on_hand)->toBe('100.000');
    $this->assertDatabaseCount('inventory_movements', 0);
});

test('warehouse inventory supports material details and stock summaries', function () {
    $material = Material::factory()->create([
        'code' => 'MAT-WH-DETAIL',
        'name' => 'Warehouse detail material',
    ]);
    InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-WH-DETAIL',
        'quantity_on_hand' => '8.000',
        'storage_location' => 'Cold Store / Shelf A',
        'expires_at' => today()->addDays(20)->toDateString(),
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('warehouse.materials.inventory', ['search' => 'MAT-WH-DETAIL']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Warehouse/Inventory')
            ->where('inventory.data.0.code', 'MAT-WH-DETAIL')
            ->where('inventory.data.0.available_quantity', '8.000')
            ->where('inventory.data.0.total_batches', 1)
            ->where('inventory.data.0.storage_locations.0', 'Cold Store / Shelf A'));

    $this->actingAs(User::factory()->create())
        ->get(route('warehouse.materials.show', $material))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Warehouse/MaterialDetails')
            ->where('material.code', 'MAT-WH-DETAIL')
            ->where('batches.0.lot_code', 'LOT-WH-DETAIL')
            ->where('batches.0.days_remaining', 20));
});

test('expiration alerts show a calculated escalation and stock location', function () {
    $material = Material::factory()->create();
    InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-EXPIRING',
        'quantity_on_hand' => '2.000',
        'expires_at' => today()->addDays(4)->toDateString(),
        'storage_location' => 'Cold Store / Shelf B',
    ]);
    InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-EXPIRED',
        'quantity_on_hand' => '1.000',
        'expires_at' => today()->subDays(2)->toDateString(),
    ]);
    InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-HELD',
        'quantity_on_hand' => '3.000',
        'quality_status' => InventoryBatchQualityStatus::Held,
        'expires_at' => today()->addDays(3)->toDateString(),
    ]);
    InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-EMPTY',
        'quantity_on_hand' => '0.000',
        'expires_at' => today()->addDays(2)->toDateString(),
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('warehouse.alerts'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Warehouse/Alerts')
            ->has('batches.data', 3)
            ->where('batches.data.0.batch_number', 'LOT-EXPIRED')
            ->where('batches.data.0.escalation_status', 'Escalate now')
            ->where('batches.data.1.batch_number', 'LOT-HELD')
            ->where('batches.data.1.escalation_status', 'QA review required')
            ->where('batches.data.2.batch_number', 'LOT-EXPIRING')
            ->where('batches.data.2.storage_location', 'Cold Store / Shelf B')
            ->where('batches.data.2.escalation_status', 'Urgent'));
});

test('warehouse movement history shows stock in and out records with their actor', function () {
    $actor = User::factory()->create();
    $batch = InventoryBatch::factory()->create(['storage_location' => 'Rack A']);
    $batch->recordStockOut('1.000', $actor, 'Line 1', 'Production issue', 'REQ-1');

    $this->actingAs($actor)
        ->get(route('warehouse.movements', ['type' => InventoryMovementType::StockOut->value]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Warehouse/Movements')
            ->has('movements.data', 1)
            ->where('movements.data.0.on_hand_delta', '-1.000')
            ->where('movements.data.0.destination_location', 'Line 1')
            ->where('movements.data.0.actor', $actor->name));
});

test('warehouse receiving and movement pages require authentication', function () {
    $this->get(route('warehouse.receipts.create'))->assertRedirect(route('login'));
    $this->get(route('warehouse.movements'))->assertRedirect(route('login'));
    $this->get(route('warehouse.alerts'))->assertRedirect(route('login'));
});
