<?php

use App\Http\Controllers\WarehouseAuthController;
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

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('admin', function () {
        return Inertia::render('admin/index');
    })->name('admin');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
