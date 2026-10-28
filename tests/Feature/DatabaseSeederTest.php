<?php

use App\Models\Material;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\AnalyticsDemoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

test('database seeder creates repeatable ERP data without adding users', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-28 16:00:00'));
    $existingUser = User::factory()->create([
        'email' => 'test@example.com',
        'role' => 'Warehouse Staff',
    ]);
    $userCount = User::query()->count();

    Artisan::call('db:seed', [
        '--class' => DatabaseSeeder::class,
        '--no-interaction' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => DatabaseSeeder::class,
        '--no-interaction' => true,
    ]);

    expect(User::query()->count())->toBe($userCount)
        ->and($existingUser->fresh()->role)->toBe('Warehouse Staff')
        ->and(User::query()->where('email', 'like', 'sample.%@example.test')->exists())->toBeFalse()
        ->and(DB::table('purchase_orders')->where('order_number', 'SAMPLE-PO-04')->count())->toBe(1)
        ->and(DB::table('goods_receipts')->where('receipt_number', 'SAMPLE-GR-04')->count())->toBe(1)
        ->and(DB::table('purchase_order_items')
            ->join('purchase_order_item_allocations', 'purchase_order_items.id', '=', 'purchase_order_item_allocations.purchase_order_item_id')
            ->where('purchase_order_items.purchase_order_id', function ($query) {
                $query->select('id')->from('purchase_orders')->where('order_number', 'SAMPLE-PO-04');
            })
            ->count())->toBe(3)
        ->and(DB::table('inventory_movements')
            ->where('movement_type', 'stock_out')
            ->where('source_reference', 'SAMPLE-PMR-04')
            ->count())->toBe(1)
        ->and(DB::table('inventory_batches')
            ->where('storage_location', 'SAMPLE-WH-01 / A-01')
            ->count())->toBeGreaterThan(0)
        ->and(Material::query()->whereIn('code', [
            'RM-SUGAR-001',
            'RM-COCOA-001',
            'RM-MILK-001',
            'RM-OIL-001',
            'PKG-BOTTLE-001',
            'PKG-CAP-001',
            'PKG-LABEL-001',
        ])->count())->toBe(7)
        ->and(DB::table('purchase_orders')
            ->where('order_number', 'like', 'ANL-PO-%')
            ->where('currency_code', 'PHP')
            ->count())->toBe(4)
        ->and(DB::table('inventory_movements')
            ->where('source_reference', 'like', 'ANL-REC-%')
            ->count())->toBe(28)
        ->and(DB::table('inventory_movements')
            ->where('source_reference', 'like', 'ANL-PMR-%')
            ->count())->toBe(27);

    $this->assertDatabaseMissing('inventory_movements', [
        ['occurred_at', '>', '2026-10-28 16:00:00'],
    ]);
});

test('analytics seeder posts four months of peso transactions without future or duplicate movements', function (string $date, string $milkExpiry) {
    $referenceTime = CarbonImmutable::parse($date.' 16:00:00');
    $this->travelTo($referenceTime);
    User::factory()->create(['role' => 'Warehouse Staff']);

    $this->seed(AnalyticsDemoSeeder::class);
    $this->seed(AnalyticsDemoSeeder::class);

    expect(DB::table('goods_receipts')->orderBy('receipt_number')->pluck('receipt_number')->all())->toBe([
        'ANL-REC-202607',
        'ANL-REC-202608',
        'ANL-REC-202609',
        'ANL-REC-202610',
    ]);
    expect(DB::table('goods_receipts')->where('status', 'posted')->count())->toBe(4);
    expect(DB::table('purchase_orders')->where('status', 'ordered')->where('currency_code', 'PHP')->count())->toBe(4);
    expect(DB::table('purchase_order_item_allocations')->count())->toBe(28);
    expect(DB::table('deliveries')->where('status', 'received')->count())->toBe(4);
    expect(DB::table('inventory_batches')->where('lot_code', 'like', 'ANL-%')->count())->toBe(28);
    expect(DB::table('production_material_requests')->where('status', 'fulfilled')->count())->toBe(27);
    expect(DB::table('inventory_movements')->where('movement_type', 'goods_receipt_accepted')->count())->toBe(28);
    expect(DB::table('inventory_movements')->where('movement_type', 'stock_out')->count())->toBe(27);
    expect(DB::table('inventory_movements')->where('occurred_at', '>', $referenceTime)->exists())->toBeFalse();
    expect(DB::table('procurement_activities')->where('occurred_at', '>', $referenceTime)->exists())->toBeFalse();
    expect(now()->toDateTimeString())->toBe($date.' 16:00:00');

    $this->assertDatabaseHas('inventory_batches', [
        'lot_code' => 'ANL-202610-RM-MILK-001',
        'expires_at' => $milkExpiry.' 00:00:00',
        'quantity_on_hand' => '160.000',
    ]);
    $this->assertDatabaseHas('inventory_batches', [
        'lot_code' => 'ANL-202610-RM-COCOA-001',
        'quality_status' => 'held',
        'quantity_on_hand' => '360.000',
    ]);
})->with([
    'current month' => ['2026-10-28', '2026-11-15'],
    'first day of month' => ['2026-10-01', '2026-10-19'],
]);

test('database seeder preserves an existing account with the default email', function () {
    $existingUser = User::factory()->create([
        'name' => 'Registered Finance User',
        'email' => 'test@example.com',
        'role' => 'Finance Staff',
    ]);

    Artisan::call('db:seed', [
        '--class' => DatabaseSeeder::class,
        '--no-interaction' => true,
    ]);

    expect($existingUser->fresh()->name)->toBe('Registered Finance User')
        ->and($existingUser->fresh()->role)->toBe('Finance Staff')
        ->and(User::query()->where('email', 'test@example.com')->count())->toBe(1)
        ->and(DB::table('purchase_orders')->where('order_number', 'SAMPLE-PO-04')->exists())->toBeTrue();
});

test('database seeder leaves users empty when no users exist', function () {
    Artisan::call('db:seed', [
        '--class' => DatabaseSeeder::class,
        '--no-interaction' => true,
    ]);

    expect(User::query()->count())->toBe(0)
        ->and(DB::table('purchase_orders')->where('order_number', 'like', 'SAMPLE-%')->exists())->toBeFalse()
        ->and(Artisan::output())->toContain('Create a real user and run db:seed again.');
});
