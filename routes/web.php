<?php

use App\Http\Controllers\DistributionController;
use App\Http\Controllers\ManagementWorkspaceController;
use App\Http\Controllers\ProcurementWorkspaceController;
use App\Http\Controllers\ProductionWorkspaceController;
use App\Http\Controllers\SystemAdministratorController;
use App\Http\Controllers\Warehouse\WarehouseController;
use App\Http\Controllers\WarehouseAuthController;
use App\Http\Controllers\WarehouseWorkspaceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

Route::get('/', function () {
    return response()->file(base_path('product-monitor/index.html'));
})->name('home');

Route::prefix('product-monitor')->group(function () {
    Route::get('brand-logo.png', function (): BinaryFileResponse {
        return response()->file(resource_path('js/lib/Universal RObina logo.png'), [
            'Content-Type' => 'image/png',
        ]);
    })->name('product-monitor.brand-logo');

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
        abort_unless(in_array($screen, ['auth', 'dashboard', 'inventory', 'operations', 'alerts', 'settings', 'role-dashboard', 'sales-and-distribution-staff', 'system-administrator', 'warehouse-staff', 'procurement-staff', 'production-staff', 'management'], true), 404);

        return response()->file(base_path("product-monitor/screens/{$screen}.js"), [
            'Content-Type' => 'text/javascript; charset=UTF-8',
        ]);
    });
});

Route::prefix('warehouse/auth')->name('warehouse.auth.')->group(function () {
    Route::get('session', [WarehouseAuthController::class, 'session'])->name('session');
    Route::post('login', [WarehouseAuthController::class, 'login'])->middleware('guest')->name('login');
    Route::post('change-password', [WarehouseAuthController::class, 'changePassword'])->middleware('auth')->name('change-password');
    Route::post('logout', [WarehouseAuthController::class, 'logout'])->middleware('auth')->name('logout');
});

Route::prefix('warehouse/workspace')->name('warehouse.workspace.')->middleware(['auth', 'password.changed'])->group(function () {
    Route::patch('products/{productId}/status', [WarehouseWorkspaceController::class, 'updateProductStatus'])
        ->where('productId', '[A-Za-z0-9-]+')
        ->name('products.status.update');
    Route::post('alerts/read', [WarehouseWorkspaceController::class, 'markAlertsRead'])->name('alerts.read-all');
    Route::delete('alerts/{alertId}/read', [WarehouseWorkspaceController::class, 'markAlertUnread'])
        ->where('alertId', '[A-Za-z0-9-]+')
        ->name('alerts.unread');
    Route::post('alerts/{alertId}/read', [WarehouseWorkspaceController::class, 'markAlertRead'])
        ->where('alertId', '[A-Za-z0-9-]+')
        ->name('alerts.read');
});

Route::prefix('warehouse/admin')->name('warehouse.admin.')->middleware(['auth', 'password.changed'])->group(function () {
    Route::get('dashboard', [SystemAdministratorController::class, 'dashboard'])->name('dashboard');
    Route::get('users', [SystemAdministratorController::class, 'users'])->name('users.index');
    Route::post('users', [SystemAdministratorController::class, 'storeUser'])->name('users.store');
    Route::patch('users/{user}', [SystemAdministratorController::class, 'updateUser'])->name('users.update');
});

Route::prefix('warehouse/procurement')->name('warehouse.procurement.')->middleware(['auth', 'password.changed'])->group(function () {
    Route::get('dashboard', [ProcurementWorkspaceController::class, 'dashboard'])->name('dashboard');
    Route::get('materials', [ProcurementWorkspaceController::class, 'materials'])->name('materials');
    Route::get('requests', [ProcurementWorkspaceController::class, 'requests'])->name('requests');
    Route::post('requests', [ProcurementWorkspaceController::class, 'storeRequest'])->name('requests.store');
    Route::post('requests/{purchaseRequest}/submit', [ProcurementWorkspaceController::class, 'submitRequest'])->name('requests.submit');
    Route::post('requests/{purchaseRequest}/decision', [ProcurementWorkspaceController::class, 'decideRequest'])->name('requests.decision');
    Route::post('orders', [ProcurementWorkspaceController::class, 'storeOrder'])->name('orders.store');
    Route::post('orders/{purchaseOrder}/place', [ProcurementWorkspaceController::class, 'placeOrder'])->name('orders.place');
    Route::get('suppliers', [ProcurementWorkspaceController::class, 'suppliers'])->name('suppliers');
    Route::post('suppliers', [ProcurementWorkspaceController::class, 'storeSupplier'])->name('suppliers.store');
    Route::get('deliveries', [ProcurementWorkspaceController::class, 'deliveries'])->name('deliveries');
    Route::post('deliveries', [ProcurementWorkspaceController::class, 'storeDelivery'])->name('deliveries.store');
    Route::post('deliveries/{delivery}/receipts', [ProcurementWorkspaceController::class, 'storeReceipt'])->name('deliveries.receipts.store');
    Route::get('reports', [ProcurementWorkspaceController::class, 'reports'])->name('reports');
});

Route::prefix('warehouse/production')->name('warehouse.production.')->middleware(['auth', 'password.changed'])->group(function () {
    Route::get('dashboard', [ProductionWorkspaceController::class, 'dashboard'])->name('dashboard');
    Route::get('materials', [ProductionWorkspaceController::class, 'materials'])->name('materials');
    Route::get('requests', [ProductionWorkspaceController::class, 'requests'])->name('requests');
    Route::post('requests', [ProductionWorkspaceController::class, 'storeRequest'])->name('requests.store');
    Route::post('requests/{materialRequest}/decision', [ProductionWorkspaceController::class, 'decideRequest'])->name('requests.decision');
    Route::post('requests/{materialRequest}/fulfill', [ProductionWorkspaceController::class, 'fulfillRequest'])->name('requests.fulfill');
    Route::get('issued-materials', [ProductionWorkspaceController::class, 'issuedMaterials'])->name('issued-materials');
    Route::get('batches', [ProductionWorkspaceController::class, 'batches'])->name('batches');
});

Route::prefix('warehouse/management')->name('warehouse.management.')->middleware(['auth', 'password.changed'])->group(function () {
    Route::get('dashboard', [ManagementWorkspaceController::class, 'dashboard'])->name('dashboard');
});

Route::middleware(['auth', 'password.changed'])->group(function () {
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
