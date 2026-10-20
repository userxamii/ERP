<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('signup stores the selected role and a hashed password in the users table', function () {
    $response = $this->postJson(route('warehouse.auth.register'), [
        'name' => 'Finance User',
        'email' => 'finance@example.com',
        'role' => 'Finance Staff',
        'password' => 'warehouse-password',
        'password_confirmation' => 'warehouse-password',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Account created. Sign in with your new email and password.');

    $user = User::query()->where('email', 'finance@example.com')->firstOrFail();

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => 'Finance Staff',
    ]);
    $this->assertModelExists($user);
    expect(Hash::check('warehouse-password', $user->password))->toBeTrue();
    $this->assertGuest();
});

test('signup rejects roles that are not offered by the warehouse workspace', function () {
    $response = $this->postJson(route('warehouse.auth.register'), [
        'name' => 'Unauthorized User',
        'email' => 'invalid-role@example.com',
        'role' => 'Owner',
        'password' => 'warehouse-password',
        'password_confirmation' => 'warehouse-password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    $this->assertDatabaseMissing('users', ['email' => 'invalid-role@example.com']);
});

test('signup cannot assign the system administrator role', function () {
    $response = $this->postJson(route('warehouse.auth.register'), [
        'name' => 'Unauthorized Administrator',
        'email' => 'untrusted-admin@example.com',
        'role' => 'System Administrator',
        'password' => 'warehouse-password',
        'password_confirmation' => 'warehouse-password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    $this->assertDatabaseMissing('users', ['email' => 'untrusted-admin@example.com']);
});

test('sign-in authenticates the saved account and returns its assigned role', function () {
    $user = User::factory()->create([
        'email' => 'qa@example.com',
        'role' => 'Quality Assurance (QA)',
        'password' => 'warehouse-password',
    ]);

    $response = $this->postJson(route('warehouse.auth.login'), [
        'email' => 'qa@example.com',
        'password' => 'warehouse-password',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.email', 'qa@example.com')
        ->assertJsonPath('user.role', 'Quality Assurance (QA)');

    $this->assertAuthenticatedAs($user);
});

test('successful and failed warehouse sign-ins are recorded for administrator activity', function () {
    User::factory()->create([
        'email' => 'activity@example.com',
        'password' => 'warehouse-password',
    ]);

    $this->postJson(route('warehouse.auth.login'), [
        'email' => 'activity@example.com',
        'password' => 'warehouse-password',
    ])->assertOk();

    $this->postJson(route('warehouse.auth.logout'))->assertOk();

    $this->postJson(route('warehouse.auth.login'), [
        'email' => 'activity@example.com',
        'password' => 'incorrect-password',
    ])->assertUnprocessable();

    $this->assertDatabaseHas('system_activities', [
        'event_type' => 'login_succeeded',
        'description' => 'User signed in.',
    ]);
    $this->assertDatabaseHas('system_activities', [
        'event_type' => 'login_failed',
        'description' => 'Failed sign-in attempt.',
    ]);
});

test('sign-in rejects an incorrect password without starting a session', function () {
    User::factory()->create([
        'email' => 'wrong-password@example.com',
        'password' => 'warehouse-password',
    ]);

    $response = $this->postJson(route('warehouse.auth.login'), [
        'email' => 'wrong-password@example.com',
        'password' => 'incorrect-password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

test('session endpoint returns the saved role and logout ends the database-backed session', function () {
    $user = User::factory()->create([
        'role' => 'Production Staff',
    ]);

    $this->actingAs($user)
        ->getJson(route('warehouse.auth.session'))
        ->assertOk()
        ->assertJsonPath('authenticated', true)
        ->assertJsonPath('user.role', 'Production Staff');

    $this->postJson(route('warehouse.auth.logout'))
        ->assertOk()
        ->assertJsonPath('message', 'Signed out.');

    $this->assertGuest();
});
