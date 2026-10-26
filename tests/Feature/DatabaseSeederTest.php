<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

test('database seeder can run repeatedly without duplicating the default account', function () {
    Artisan::call('db:seed', [
        '--class' => DatabaseSeeder::class,
        '--no-interaction' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => DatabaseSeeder::class,
        '--no-interaction' => true,
    ]);

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect(User::query()->where('email', 'test@example.com')->count())->toBe(1)
        ->and($user->name)->toBe('Test User')
        ->and($user->role)->toBe('Warehouse Staff')
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
        ->and(DB::table('users')
            ->join('departments', 'users.department_id', '=', 'departments.id')
            ->where('users.email', 'sample.production@example.test')
            ->value('departments.name'))->toBe('Production')
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
        ->and(DB::table('departments')->where('name', 'Production')->exists())->toBeTrue();
});
