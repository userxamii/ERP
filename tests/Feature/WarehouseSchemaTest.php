<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('warehouse schema includes all requested tables', function () {
    $tables = [
        'roles',
        'departments',
        'users',
        'materials',
        'suppliers',
        'warehouses',
        'inventory_batches',
        'material_requests',
        'material_request_items',
        'stock_movements',
        'quality_inspections',
        'notifications',
        'audit_logs',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

test('users can reference roles and departments without removing the existing role label', function () {
    $roleId = DB::table('roles')->insertGetId([
        'name' => 'Warehouse Staff',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $departmentId = DB::table('departments')->insertGetId([
        'code' => 'WH',
        'name' => 'Warehouse',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::factory()->create([
        'role' => 'Warehouse Staff',
        'role_id' => $roleId,
        'department_id' => $departmentId,
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => 'Warehouse Staff',
        'role_id' => $roleId,
        'department_id' => $departmentId,
    ]);

    DB::table('roles')->where('id', $roleId)->delete();
    DB::table('departments')->where('id', $departmentId)->delete();

    expect($user->fresh()->role)->toBe('Warehouse Staff')
        ->and($user->fresh()->role_id)->toBeNull()
        ->and($user->fresh()->department_id)->toBeNull();
});
