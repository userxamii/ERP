<?php

use App\Models\SystemActivity;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('system administrators can view all registered accounts and their roles', function () {
    $administrator = User::factory()->create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'role' => 'System Administrator',
    ]);
    expect($administrator->is_active)->toBeTrue();
    User::factory()->create([
        'name' => 'Warehouse User',
        'email' => 'warehouse@example.com',
        'role' => 'Warehouse Staff',
        'department' => 'Warehouse',
    ]);

    $response = $this->actingAs($administrator)
        ->getJson(route('warehouse.admin.users.index'));

    $response
        ->assertOk()
        ->assertJsonCount(2, 'users')
        ->assertJsonPath('users.0.name', 'Admin User')
        ->assertJsonPath('users.0.role', 'System Administrator')
        ->assertJsonPath('users.1.name', 'Warehouse User')
        ->assertJsonPath('users.1.role', 'Warehouse Staff')
        ->assertJsonPath('users.1.department', 'Warehouse')
        ->assertJsonPath('users.1.isActive', true);

    $this->assertArrayNotHasKey('password', $response->json('users.0'));
    $this->assertArrayNotHasKey('remember_token', $response->json('users.0'));
});

test('the account directory requires authentication', function () {
    $this->getJson(route('warehouse.admin.users.index'))
        ->assertUnauthorized();
});

test('non-administrators cannot view the account directory', function () {
    $user = User::factory()->create([
        'role' => 'Warehouse Staff',
    ]);

    $this->actingAs($user)
        ->getJson(route('warehouse.admin.users.index'))
        ->assertForbidden();
});

test('system administrators can view account and system health metrics', function () {
    $administrator = User::factory()->create([
        'role' => 'System Administrator',
    ]);
    User::factory()->create(['role' => 'Warehouse Staff']);
    SystemActivity::query()->create([
        'user_id' => $administrator->id,
        'event_type' => 'login_failed',
        'description' => 'Failed sign-in attempt.',
    ]);
    SystemActivity::query()->create([
        'user_id' => $administrator->id,
        'event_type' => 'login_succeeded',
        'description' => 'User signed in.',
    ]);

    $this->actingAs($administrator)
        ->getJson(route('warehouse.admin.dashboard'))
        ->assertOk()
        ->assertJsonPath('metrics.registeredUsers', 2)
        ->assertJsonPath('metrics.activeUsers', 2)
        ->assertJsonPath('metrics.failedLogins', 1)
        ->assertJsonPath('metrics.successfulLogins', 1)
        ->assertJsonPath('health.api', 'Operational')
        ->assertJsonPath('health.database', 'Operational')
        ->assertJsonPath('recentActivity.0.eventType', 'login_succeeded')
        ->assertJsonPath('recentActivity.1.eventType', 'login_failed');
});

test('system administrators can create accounts and send password setup links', function () {
    Notification::fake();
    $administrator = User::factory()->create([
        'role' => 'System Administrator',
    ]);

    $response = $this->actingAs($administrator)
        ->postJson(route('warehouse.admin.users.store'), [
            'name' => 'New Staff',
            'email' => 'new.staff@example.com',
            'role' => 'Warehouse Staff',
            'department' => 'Warehouse',
        ]);

    $response->assertCreated()
        ->assertJsonPath('user.name', 'New Staff')
        ->assertJsonPath('user.role', 'Warehouse Staff')
        ->assertJsonPath('user.department', 'Warehouse')
        ->assertJsonPath('user.isActive', true)
        ->assertJsonPath('resetLinkSent', true);

    $newUser = User::query()->where('email', 'new.staff@example.com')->firstOrFail();
    Notification::assertSentTo($newUser, ResetPassword::class);
    $this->assertDatabaseHas('system_activities', [
        'actor_user_id' => $administrator->id,
        'user_id' => $newUser->id,
        'event_type' => 'user_created',
    ]);
});

test('system administrators can edit and deactivate accounts while preserving history', function () {
    $administrator = User::factory()->create([
        'role' => 'System Administrator',
    ]);
    $user = User::factory()->create([
        'role' => 'Warehouse Staff',
        'department' => 'Warehouse',
    ]);

    $this->actingAs($administrator)
        ->patchJson(route('warehouse.admin.users.update', $user), [
            'name' => 'Updated Staff',
            'department' => 'Receiving',
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('user.name', 'Updated Staff')
        ->assertJsonPath('user.department', 'Receiving')
        ->assertJsonPath('user.isActive', false);

    $this->assertModelExists($user->refresh());
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'is_active' => false,
        'department' => 'Receiving',
    ]);
    $this->assertDatabaseHas('system_activities', [
        'actor_user_id' => $administrator->id,
        'user_id' => $user->id,
        'event_type' => 'user_updated',
    ]);
});

test('deactivated accounts cannot use authenticated endpoints', function () {
    $user = User::factory()->create([
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->getJson(route('warehouse.auth.session'))
        ->assertForbidden()
        ->assertJsonPath('message', 'This account is inactive.');
});

test('the last active administrator cannot be deactivated', function () {
    $administrator = User::factory()->create([
        'role' => 'System Administrator',
    ]);

    $this->actingAs($administrator)
        ->patchJson(route('warehouse.admin.users.update', $administrator), [
            'is_active' => false,
        ])
        ->assertUnprocessable();

    $this->assertDatabaseHas('users', [
        'id' => $administrator->id,
        'is_active' => true,
    ]);
});

test('system administrators can send password reset links for an account', function () {
    Notification::fake();
    $administrator = User::factory()->create([
        'role' => 'System Administrator',
    ]);
    $user = User::factory()->create();

    $this->actingAs($administrator)
        ->postJson(route('warehouse.admin.users.password-reset', $user))
        ->assertOk()
        ->assertJsonPath('message', 'A password reset link has been sent by email.');

    Notification::assertSentTo($user, ResetPassword::class);
});

test('non-administrators cannot access system health or change accounts', function () {
    $user = User::factory()->create([
        'role' => 'Warehouse Staff',
    ]);
    $target = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('warehouse.admin.dashboard'))
        ->assertForbidden();

    $this->actingAs($user)
        ->patchJson(route('warehouse.admin.users.update', $target), [
            'role' => 'System Administrator',
        ])
        ->assertForbidden();
});

test('a trusted console command can promote an existing account to system administrator', function () {
    $user = User::factory()->create([
        'email' => 'promote@example.com',
        'role' => 'Warehouse Staff',
    ]);

    $this->artisan('users:promote-administrator', ['email' => $user->email])
        ->assertExitCode(0);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => 'System Administrator',
    ]);
});

test('the administrator provisioning command fails when the account does not exist', function () {
    $this->artisan('users:promote-administrator', ['email' => 'missing@example.com'])
        ->assertExitCode(1);
});
