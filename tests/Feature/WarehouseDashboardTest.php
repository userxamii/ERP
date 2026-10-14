<?php

use App\InventoryBatchQualityStatus;
use App\Models\InventoryBatch;
use App\Models\Material;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('warehouse dashboard shows filtered inventory and calculated stock metrics', function () {
    $material = Material::factory()->create([
        'minimum_stock_qty' => '100.000',
    ]);
    InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-AVAILABLE',
        'quantity_on_hand' => '150.000',
        'expires_at' => today()->addDays(10)->toDateString(),
    ]);
    InventoryBatch::factory()->for($material)->create([
        'lot_code' => 'LOT-HELD',
        'quality_status' => InventoryBatchQualityStatus::Held,
        'quantity_on_hand' => '50.000',
        'expires_at' => today()->addDays(5)->toDateString(),
    ]);

    $this->actingAs(User::factory()->create(['role' => 'Warehouse Staff']))
        ->get(route('warehouse.dashboard', ['tab' => 'held', 'search' => 'LOT-HELD']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Warehouse/Dashboard')
            ->where('stats.active_materials', 1)
            ->where('stats.low_stock_materials', 0)
            ->where('stats.expiring_batches', 1)
            ->where('stats.review_batches', 1)
            ->where('filters.tab', 'held')
            ->where('filters.search', 'LOT-HELD')
            ->has('inventory.data', 1)
            ->where('inventory.data.0.batch_number', 'LOT-HELD')
            ->where('inventory.data.0.material_code', $material->code)
            ->where('inventory.data.0.quality_status', 'held'));
});

test('warehouse dashboard requires authentication', function () {
    $this->get(route('warehouse.dashboard'))
        ->assertRedirect(route('login'));
});
