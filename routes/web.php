<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Warehouse\WarehouseController;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // Warehouse Staff Dashboard Routes
    Route::prefix('warehouse')->name('warehouse.')->group(function () {
        Route::get('/dashboard', [WarehouseController::class, 'index'])->name('dashboard');
        Route::post('/tasks/{id}/status', [WarehouseController::class, 'updateTaskStatus'])->name('tasks.status');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';