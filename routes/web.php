<?php

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
