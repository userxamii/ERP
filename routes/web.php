<?php

use App\Http\Controllers\DistributionController;
use App\Http\Controllers\Warehouse\WarehouseController;
use App\Http\Controllers\WarehouseAuthController;
use App\Http\Controllers\WarehouseWorkspaceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return response()->file(base_path('product-monitor/index.html'));
})->name('home');

Route::prefix('product-monitor')->group(function () {
    Route::get('style.css', function () {
        return response()->file(base_path('product-monitor/style.css'), [
            'Content-Type' => 'text/css; charset=UTF-8',
        ]);
    });

    Route::get('script.js', function () {
        return response()->file(base_path('product-monitor/script.js'), [
            'Content-Type' => 'text/javascript; charset=UTF-8',
        ]);
    });

    Route::get('main.js', function () {
        return response()->file(base_path('product-monitor/main.js'), [
            'Content-Type' => 'text/javascript; charset=UTF-8',
        ]);
    });

    Route::get('screens/{screen}.js', function (string $screen) {
        abort_unless(in_array($screen, ['auth', 'dashboard', 'inventory', 'operations', 'alerts', 'settings', 'role-dashboard', 'sales-and-distribution-staff', 'warehouse-staff'], true), 404);

        return response()->file(base_path("product-monitor/screens/{$screen}.js"), [
            'Content-Type' => 'text/javascript; charset=UTF-8',
        ]);
    });
});

Route::prefix('warehouse/auth')->name('warehouse.auth.')->group(function () {
    Route::get('session', [WarehouseAuthController::class, 'session'])->name('session');
    Route::post('register', [WarehouseAuthController::class, 'register'])->middleware('guest')->name('register');
    Route::post('login', [WarehouseAuthController::class, 'login'])->middleware('guest')->name('login');
    Route::post('logout', [WarehouseAuthController::class, 'logout'])->middleware('auth')->name('logout');
});

Route::prefix('warehouse/workspace')->name('warehouse.workspace.')->middleware('auth')->group(function () {
    Route::patch('products/{productId}/status', [WarehouseWorkspaceController::class, 'updateProductStatus'])
        ->where('productId', '[A-Za-z0-9-]+')
        ->name('products.status.update');
    Route::post('alerts/read', [WarehouseWorkspaceController::class, 'markAlertsRead'])->name('alerts.read-all');
    Route::post('alerts/{alertId}/read', [WarehouseWorkspaceController::class, 'markAlertRead'])
        ->where('alertId', '[A-Za-z0-9-]+')
        ->name('alerts.read');
});

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('admin', function () {
        return Inertia::render('admin/index');
    })->name('admin');
    // Warehouse Staff Dashboard Routes
    Route::prefix('warehouse')->name('warehouse.')->group(function () {
        Route::get('/dashboard', [WarehouseController::class, 'index'])->name('dashboard');
        Route::get('/materials', [WarehouseController::class, 'inventory'])->name('materials.inventory');
        Route::get('/materials/{material}', [WarehouseController::class, 'showMaterial'])->name('materials.show');
        Route::get('/receipts/create', [WarehouseController::class, 'receive'])->name('receipts.create');
        Route::post('/receipts', [WarehouseController::class, 'storeReceipt'])->name('receipts.store');
        Route::get('/movements', [WarehouseController::class, 'movements'])->name('movements');
        Route::post('/stock-outs', [WarehouseController::class, 'storeStockOut'])->name('stock-outs.store');
        Route::get('/alerts', [WarehouseController::class, 'alerts'])->name('alerts');
    });

    Route::prefix('distribution')->name('distribution.')->group(function () {
        Route::get('/overview', [DistributionController::class, 'overview'])->name('overview');
        Route::get('/availability-reports', [DistributionController::class, 'reports'])->name('reports');
        Route::get('/notifications', [DistributionController::class, 'notifications'])->name('notifications');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
