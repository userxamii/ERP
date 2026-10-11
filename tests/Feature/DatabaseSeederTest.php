<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

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
        ->and($user->role)->toBe('Warehouse Staff');
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

    expect(User::query()->count())->toBe(1)
        ->and($existingUser->fresh()->name)->toBe('Registered Finance User')
        ->and($existingUser->fresh()->role)->toBe('Finance Staff');
});
