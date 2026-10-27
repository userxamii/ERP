<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

test('database seeder creates repeatable ERP data without adding users', function () {
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
            ->count())->toBeGreaterThan(0);
});

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
