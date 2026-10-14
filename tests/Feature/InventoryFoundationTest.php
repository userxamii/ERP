<?php

use App\Http\Requests\Inventory\InventoryRequest;
use App\Http\Requests\Inventory\StoreInventoryBatchRequest;
use App\Http\Requests\Inventory\StoreMaterialCategoryRequest;
use App\Http\Requests\Inventory\StoreMaterialRequest;
use App\Http\Requests\Inventory\StoreMaterialStockReservationRequest;
use App\Http\Requests\Inventory\StoreUnitOfMeasureRequest;
use App\InventoryBatchQualityStatus;
use App\MaterialStockReservationStatus;
use App\Models\InventoryBatch;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialStockReservation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

test('inventory models expose the Stage 1 relationships and enum casts', function () {
    $material = Material::factory()->create();
    $batch = InventoryBatch::factory()->for($material)->create();
    $reservation = MaterialStockReservation::factory()->for($material)->create();

    expect($material->category)->toBeInstanceOf(MaterialCategory::class)
        ->and($material->unitOfMeasure)->toBeInstanceOf(UnitOfMeasure::class)
        ->and($material->inventoryBatches->first()->is($batch))->toBeTrue()
        ->and($material->stockReservations->first()->is($reservation))->toBeTrue()
        ->and($batch->material->is($material))->toBeTrue()
        ->and($batch->quality_status)->toBe(InventoryBatchQualityStatus::Available)
        ->and($reservation->status)->toBe(MaterialStockReservationStatus::Active)
        ->and($reservation->createdBy)->toBeInstanceOf(User::class)
        ->and($reservation->quantity)->toBe('10.000');
});

test('inventory foreign keys preserve referenced records and detach deleted categories', function () {
    $category = MaterialCategory::factory()->create();
    $material = Material::factory()->for($category, 'category')->create();
    $batch = InventoryBatch::factory()->for($material)->create();
    $unit = $material->unitOfMeasure;

    $category->delete();
    $material->refresh();

    expect($material->material_category_id)->toBeNull();

    expect(fn () => $unit->delete())->toThrow(QueryException::class)
        ->and(fn () => $material->delete())->toThrow(QueryException::class);
});

test('inventory catalog validation enforces unique identifiers and supported unit precision', function () {
    $category = MaterialCategory::factory()->create();
    $unit = UnitOfMeasure::factory()->create();

    $categoryValidator = Validator::make(
        ['name' => $category->name],
        (new StoreMaterialCategoryRequest)->rules(),
    );
    $unitValidator = Validator::make(
        ['code' => $unit->code, 'name' => 'Duplicate unit', 'decimal_places' => 4],
        (new StoreUnitOfMeasureRequest)->rules(),
    );

    expect($categoryValidator->fails())->toBeTrue()
        ->and($categoryValidator->errors()->has('name'))->toBeTrue()
        ->and($unitValidator->fails())->toBeTrue()
        ->and($unitValidator->errors()->has('code'))->toBeTrue()
        ->and($unitValidator->errors()->has('decimal_places'))->toBeTrue();
});

test('material validation rejects reorder targets below minimum stock', function () {
    $material = Material::factory()->make();

    $validator = Validator::make([
        'code' => $material->code,
        'name' => $material->name,
        'unit_of_measure_id' => UnitOfMeasure::factory()->create()->id,
        'minimum_stock_qty' => '200.000',
        'reorder_to_qty' => '100.000',
    ], (new StoreMaterialRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('reorder_to_qty'))->toBeTrue();
});

test('inventory batch validation rejects excess on-hand quantity and invalid quality status', function () {
    $material = Material::factory()->create();
    InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-TEST-001',
    ]);

    $batchData = [
        'material_id' => $material->id,
        'lot_code' => 'LOT-TEST-001',
        'received_at' => '2026-10-01',
        'quantity_received' => '10.000',
        'quantity_on_hand' => '11.000',
        'quality_status' => 'unknown',
    ];
    $request = StoreInventoryBatchRequest::create('/', 'POST', $batchData);
    $validator = Validator::make($batchData, $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('lot_code'))->toBeTrue()
        ->and($validator->errors()->has('quantity_on_hand'))->toBeTrue()
        ->and($validator->errors()->has('quality_status'))->toBeTrue();
});

test('reservation validation rejects non-positive quantities and client-controlled lifecycle data', function () {
    $material = Material::factory()->create();

    $validator = Validator::make([
        'material_id' => $material->id,
        'quantity' => '0.000',
        'status' => MaterialStockReservationStatus::Released->value,
        'created_by_user_id' => User::factory()->create()->id,
        'released_by_user_id' => User::factory()->create()->id,
        'released_at' => now()->toDateTimeString(),
    ], (new StoreMaterialStockReservationRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('quantity'))->toBeTrue()
        ->and($validator->errors()->has('status'))->toBeTrue()
        ->and($validator->errors()->has('created_by_user_id'))->toBeTrue()
        ->and($validator->errors()->has('released_by_user_id'))->toBeTrue()
        ->and($validator->errors()->has('released_at'))->toBeTrue();
});

test('inventory requests require an authenticated user without changing role assignments', function () {
    $request = InventoryRequest::create('/');
    $request->setUserResolver(fn () => null);

    expect($request->authorize())->toBeFalse();

    $user = User::factory()->create(['role' => 'Procurement Staff']);
    $request->setUserResolver(fn () => $user);

    expect($request->authorize())->toBeTrue()
        ->and($user->fresh()->role)->toBe('Procurement Staff');
});
