<?php

use App\InventoryBatchQualityStatus;
use App\InventoryMovementType;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductionMaterialRequest;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\AnalyticsDemoSeeder;

test('management can view filtered executive inventory and movement reports', function () {
    $manager = User::factory()->create(['role' => 'Management']);
    $warehouseStaff = User::factory()->create(['role' => 'Warehouse Staff']);
    $category = MaterialCategory::factory()->create(['name' => 'Packaging']);
    $material = Material::factory()->for($category, 'category')->create([
        'code' => 'MAT-MGMT-001',
        'name' => 'Shipping carton',
        'minimum_stock_qty' => '6.000',
    ]);
    $availableBatch = InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-MGMT-AVAILABLE',
        'received_at' => now()->subDays(45),
        'expires_at' => today()->addDays(45),
        'storage_location' => 'Rack A',
        'quantity_on_hand' => '3.000',
    ]);
    InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-MGMT-HELD',
        'storage_location' => 'Rack B',
        'quality_status' => InventoryBatchQualityStatus::Held,
        'quantity_on_hand' => '2.000',
    ]);
    InventoryMovement::record(
        InventoryMovementType::StockOut,
        $material,
        '1.000',
        '-1.000',
        '0.000',
        $warehouseStaff,
        $availableBatch,
        sourceReference: 'ISSUE-MGMT-001',
        sourceLocation: 'Rack A',
        destinationLocation: 'Production Line 1',
        reason: 'Approved production material request',
    );
    $purchaseRequest = PurchaseRequest::factory()->create();
    PurchaseRequestItem::factory()->for($purchaseRequest)->create();
    $purchaseRequest->submit();
    $productionRequester = User::factory()->create(['role' => 'Production Staff']);
    ProductionMaterialRequest::query()->create([
        'request_number' => 'MR-MGMT-001',
        'material_id' => $material->getKey(),
        'requested_by_user_id' => $productionRequester->getKey(),
        'requested_qty' => '2.000',
        'purpose' => 'Scheduled production material requirement.',
    ]);

    $this->actingAs($manager)
        ->getJson(route('warehouse.management.dashboard', [
            'from' => today()->subDays(30)->toDateString(),
            'to' => today()->toDateString(),
            'category_id' => $category->getKey(),
            'location' => 'Rack A',
        ]))
        ->assertOk()
        ->assertJsonPath('summary.materialCount', 1)
        ->assertJsonPath('summary.onHandQuantity', '3.000')
        ->assertJsonPath('summary.lowStockCount', 1)
        ->assertJsonPath('summary.heldBatchCount', 0)
        ->assertJsonPath('summary.pendingPurchaseApprovals', 1)
        ->assertJsonPath('summary.pendingProductionApprovals', 1)
        ->assertJsonPath('summary.issuedQuantity', '1.000')
        ->assertJsonPath('inventoryReport.0.code', 'MAT-MGMT-001')
        ->assertJsonPath('inventoryReport.0.category', 'Packaging')
        ->assertJsonPath('movementReport.0.type', InventoryMovementType::StockOut->value)
        ->assertJsonPath('movementReport.0.user', $warehouseStaff->name)
        ->assertJsonPath('topMaterials.0.quantity', '1.000')
        ->assertJsonPath('topWorkCenters.0.name', 'Production Line 1')
        ->assertJsonPath('topWorkCenters.0.issuedQuantity', '1.000');
});

test('management reports reject date ranges longer than one year', function () {
    $manager = User::factory()->create(['role' => 'Management']);

    $this->actingAs($manager)
        ->getJson(route('warehouse.management.dashboard', [
            'from' => today()->subDays(400)->toDateString(),
            'to' => today()->toDateString(),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('from');
});

test('management chart data keeps different units separate and counts materials and batches', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-28 12:00:00'));
    $manager = User::factory()->create(['role' => 'Management']);
    $category = MaterialCategory::factory()->create(['name' => 'Food supplies']);
    $kilograms = UnitOfMeasure::factory()->create(['code' => 'kg']);
    $pieces = UnitOfMeasure::factory()->create(['code' => 'pcs']);
    $sugar = Material::factory()->for($category, 'category')->create([
        'unit_of_measure_id' => $kilograms->getKey(),
    ]);
    $bottles = Material::factory()->for($category, 'category')->create([
        'unit_of_measure_id' => $pieces->getKey(),
    ]);
    $sugarBatch = InventoryBatch::factory()->for($sugar)->create([
        'quantity_on_hand' => '100.000',
        'received_at' => '2026-09-15 09:00:00',
        'expires_at' => '2027-03-15',
        'storage_location' => 'Ingredient rack',
    ]);
    $bottleBatch = InventoryBatch::factory()->for($bottles)->create([
        'quantity_on_hand' => '500.000',
        'received_at' => '2026-10-15 09:00:00',
        'expires_at' => null,
        'storage_location' => 'Packaging rack',
    ]);
    InventoryMovement::record(InventoryMovementType::WarehouseReceipt, $sugar, '120.000', '120.000', '0.000', $manager, $sugarBatch);
    InventoryMovement::record(InventoryMovementType::StockOut, $sugar, '20.000', '-20.000', '0.000', $manager, $sugarBatch);
    InventoryMovement::record(InventoryMovementType::WarehouseReceipt, $bottles, '500.000', '500.000', '0.000', $manager, $bottleBatch);

    $this->actingAs($manager)
        ->getJson(route('warehouse.management.dashboard', ['from' => '2026-10-01', 'to' => '2026-10-28']))
        ->assertOk()
        ->assertJsonPath('summary.onHandByUnit.kg', '100.000')
        ->assertJsonPath('summary.onHandByUnit.pcs', '500.000')
        ->assertJsonPath('inventoryByCategory.0.materialCount', 2)
        ->assertJsonPath('agingBuckets.0.batchCount', 1)
        ->assertJsonPath('agingBuckets.1.batchCount', 1)
        ->assertJsonCount(2, 'movementTrendsByUnit')
        ->assertJsonPath('movementTrendsByUnit.0.unit', 'kg')
        ->assertJsonPath('movementTrendsByUnit.0.months.0.received', '120.000')
        ->assertJsonPath('movementTrendsByUnit.0.months.0.issued', '20.000')
        ->assertJsonPath('movementTrendsByUnit.1.unit', 'pcs')
        ->assertJsonPath('movementTrendsByUnit.1.months.0.received', '500.000');

    $this->getJson(route('warehouse.management.dashboard', [
        'from' => '2026-10-01',
        'to' => '2026-10-28',
        'location' => 'Ingredient rack',
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'movementTrendsByUnit')
        ->assertJsonPath('movementTrendsByUnit.0.unit', 'kg');
});

test('seeded material history populates management charts and peso cost exposure', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-28 16:00:00'));
    $manager = User::factory()->create(['role' => 'Management']);
    $this->seed(AnalyticsDemoSeeder::class);

    $this->actingAs($manager)
        ->getJson(route('warehouse.management.dashboard', ['from' => '2026-07-01', 'to' => '2026-10-28']))
        ->assertOk()
        ->assertJsonPath('summary.materialCount', 7)
        ->assertJsonPath('summary.inventoryValueByCurrency.PHP', '582390.00')
        ->assertJsonPath('summary.expiredBatchCount', 2)
        ->assertJsonPath('summary.expiringBatchCount', 1)
        ->assertJsonPath('summary.heldBatchCount', 1)
        ->assertJsonCount(3, 'movementTrendsByUnit')
        ->assertJsonPath('movementTrendsByUnit.0.unit', 'L')
        ->assertJsonPath('movementTrendsByUnit.1.unit', 'kg')
        ->assertJsonCount(4, 'movementTrendsByUnit.1.months')
        ->assertJsonPath('movementTrendsByUnit.1.months.0.received', '2640.000')
        ->assertJsonPath('movementTrendsByUnit.1.months.3.issued', '1640.000')
        ->assertJsonPath('movementTrendsByUnit.2.unit', 'pcs')
        ->assertJsonPath('nearExpiryCostExposure.0.currency', 'PHP')
        ->assertJsonPath('nearExpiryCostExposure.0.estimatedValue', '36000.00');
});

test('management reports require authentication', function () {
    $this->getJson(route('warehouse.management.dashboard'))
        ->assertUnauthorized();
});

test('system administrators can view the management reports', function () {
    $administrator = User::factory()->create(['role' => 'System Administrator']);

    $this->actingAs($administrator)
        ->getJson(route('warehouse.management.dashboard'))
        ->assertOk()
        ->assertJsonPath('summary.materialCount', 0);
});

test('management reports are not available to other staff roles', function () {
    $warehouseStaff = User::factory()->create(['role' => 'Warehouse Staff']);

    $this->actingAs($warehouseStaff)
        ->getJson(route('warehouse.management.dashboard'))
        ->assertForbidden();
});
