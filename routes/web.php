<?php

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

    Route::get('screens/{screen}.js', function (string $screen) {
        abort_unless(in_array($screen, ['auth', 'dashboard', 'inventory', 'operations', 'alerts', 'settings', 'sales-and-distribution-staff'], true), 404);

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
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
