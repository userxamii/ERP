<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('warehouse self-registration endpoint is unavailable', function () {
    $this->postJson('/warehouse/auth/register', [
        'name' => 'Finance User',
        'email' => 'finance@example.com',
        'role' => 'Finance Staff',
        'password' => 'warehouse-password',
        'password_confirmation' => 'warehouse-password',
    ])->assertNotFound();

    $this->assertDatabaseMissing('users', ['email' => 'finance@example.com']);
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
        ->assertJsonPath('user.role', 'Quality Assurance (QA)')
        ->assertJsonPath('mustChangePassword', false);

    $this->assertAuthenticatedAs($user);
});

<<<<<<< HEAD
=======
test('first sign-in requires changing the temporary password before workspace access', function () {
    $user = User::factory()->create([
        'email' => 'new.staff@example.com',
        'password' => 'temporary-password',
        'must_change_password' => true,
    ]);

    $this->postJson(route('warehouse.auth.login'), [
        'email' => $user->email,
        'password' => 'temporary-password',
    ])->assertOk()
        ->assertJsonPath('mustChangePassword', true)
        ->assertJsonPath('workspaceState.productStatuses', [])
        ->assertJsonPath('workspaceState.readAlertIds', []);

    $this->getJson(route('warehouse.admin.dashboard'))
        ->assertStatus(423)
        ->assertJsonPath('mustChangePassword', true);

    $this->postJson(route('warehouse.auth.change-password'), [
        'current_password' => 'temporary-password',
        'password' => 'employee-private-password',
        'password_confirmation' => 'employee-private-password',
    ])->assertOk()
        ->assertJsonPath('mustChangePassword', false);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'must_change_password' => false,
    ]);
    expect(Hash::check('employee-private-password', $user->fresh()->password))->toBeTrue();

    $this->postJson(route('warehouse.auth.logout'))->assertOk();
    $this->postJson(route('warehouse.auth.login'), [
        'email' => $user->email,
        'password' => 'employee-private-password',
    ])->assertOk()
        ->assertJsonPath('mustChangePassword', false);
});

test('temporary password cannot be changed with an incorrect current password', function () {
    $user = User::factory()->create([
        'password' => 'temporary-password',
        'must_change_password' => true,
    ]);

    $this->actingAs($user)
        ->postJson(route('warehouse.auth.change-password'), [
            'current_password' => 'incorrect-password',
            'password' => 'employee-private-password',
            'password_confirmation' => 'employee-private-password',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'must_change_password' => true,
    ]);
});

test('users who are not required to change their password cannot use the forced-change endpoint', function () {
    $user = User::factory()->create([
        'password' => 'current-password',
        'must_change_password' => false,
    ]);

    $this->actingAs($user)
        ->postJson(route('warehouse.auth.change-password'), [
            'current_password' => 'current-password',
            'password' => 'new-private-password',
            'password_confirmation' => 'new-private-password',
        ])
        ->assertStatus(409);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'must_change_password' => false,
    ]);
});

>>>>>>> 28aff9e (Integrate connected ERP workflows and sample data)
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
