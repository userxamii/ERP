<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('product status updates are persisted and returned with workspace state', function () {
    $user = User::factory()->create([
        'role' => 'Sales & Distribution Staff',
    ]);

    $this->actingAs($user)
        ->patchJson(route('warehouse.workspace.products.status.update', 'B-001'), [
            'status' => 'For Distribution',
        ])
        ->assertOk()
        ->assertJsonPath('productId', 'B-001')
        ->assertJsonPath('status', 'For Distribution');

    $this->assertDatabaseHas('product_monitor_statuses', [
        'product_id' => 'B-001',
        'status' => 'For Distribution',
    ]);

    $this->getJson(route('warehouse.auth.session'))
        ->assertOk()
        ->assertJsonPath('workspaceState.productStatuses.B-001', 'For Distribution');

    $this->actingAs($user)
        ->patchJson(route('warehouse.workspace.products.status.update', 'B-001'), [
            'status' => 'Damaged',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'Damaged');

    $this->assertDatabaseCount('product_monitor_statuses', 1);
    $this->assertDatabaseHas('product_monitor_statuses', [
        'product_id' => 'B-001',
        'status' => 'Damaged',
    ]);
});

test('product status updates reject values outside the workspace options', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('warehouse.workspace.products.status.update', 'B-001'), [
            'status' => 'Shipped',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    $this->assertDatabaseMissing('product_monitor_statuses', [
        'product_id' => 'B-001',
    ]);
});

test('product status updates require authentication', function () {
    $this->patchJson(route('warehouse.workspace.products.status.update', 'B-001'), [
        'status' => 'For Distribution',
    ])->assertUnauthorized();

    $this->assertDatabaseMissing('product_monitor_statuses', [
        'product_id' => 'B-001',
    ]);
});

test('marking an alert as read persists it for only the signed-in user', function () {
    $user = User::factory()->create([
        'role' => 'Sales & Distribution Staff',
    ]);
    $otherUser = User::factory()->create();
    $alertId = 'expiration-B-001';

    $this->actingAs($user)
        ->postJson(route('warehouse.workspace.alerts.read', $alertId))
        ->assertOk()
        ->assertJsonPath('readAlertIds.0', $alertId);

    $this->assertDatabaseHas('product_monitor_alert_reads', [
        'user_id' => $user->id,
        'alert_id' => $alertId,
    ]);

    $this->getJson(route('warehouse.auth.session'))
        ->assertOk()
        ->assertJsonPath('workspaceState.readAlertIds.0', $alertId);

    $this->actingAs($otherUser)
        ->getJson(route('warehouse.auth.session'))
        ->assertOk()
        ->assertJsonPath('workspaceState.readAlertIds', []);
});

test('undoing an alert read removes only the signed-in user read state', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $alertId = 'expiration-B-001';
    $timestamp = now();

    DB::table('product_monitor_alert_reads')->insert([
        [
            'user_id' => $user->id,
            'alert_id' => $alertId,
            'read_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ],
        [
            'user_id' => $otherUser->id,
            'alert_id' => $alertId,
            'read_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ],
    ]);

    $this->actingAs($user)
        ->deleteJson(route('warehouse.workspace.alerts.unread', $alertId))
        ->assertOk()
        ->assertJsonPath('unreadAlertIds.0', $alertId);

    $this->assertDatabaseMissing('product_monitor_alert_reads', [
        'user_id' => $user->id,
        'alert_id' => $alertId,
    ]);
    $this->assertDatabaseHas('product_monitor_alert_reads', [
        'user_id' => $otherUser->id,
        'alert_id' => $alertId,
    ]);
    $this->getJson(route('warehouse.auth.session'))
        ->assertOk()
        ->assertJsonPath('workspaceState.readAlertIds', []);
});

test('marking all active alerts as read persists every alert', function () {
    $user = User::factory()->create();
    $alertIds = ['aging-B-001', 'expiration-B-001'];

    $this->actingAs($user)
        ->postJson(route('warehouse.workspace.alerts.read-all'), [
            'alert_ids' => $alertIds,
        ])
        ->assertOk()
        ->assertJsonPath('readAlertIds', $alertIds);

    foreach ($alertIds as $alertId) {
        $this->assertDatabaseHas('product_monitor_alert_reads', [
            'user_id' => $user->id,
            'alert_id' => $alertId,
        ]);
    }
});

test('marking alerts as read validates the submitted identifiers', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('warehouse.workspace.alerts.read-all'), [
            'alert_ids' => ['invalid alert id'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('alert_ids.0');

    $this->assertDatabaseMissing('product_monitor_alert_reads', [
        'user_id' => $user->id,
    ]);
});

test('marking alerts as read requires authentication', function () {
    $this->postJson(route('warehouse.workspace.alerts.read', 'expiration-B-001'))
        ->assertUnauthorized();

    $this->postJson(route('warehouse.workspace.alerts.read-all'), [
        'alert_ids' => ['expiration-B-001'],
    ])->assertUnauthorized();
    $this->deleteJson(route('warehouse.workspace.alerts.unread', 'expiration-B-001'))
        ->assertUnauthorized();

    $this->assertDatabaseCount('product_monitor_alert_reads', 0);
});

test('sign-in returns saved workspace state for client initialization', function () {
    $user = User::factory()->create([
        'email' => 'sales@example.com',
        'password' => 'warehouse-password',
        'role' => 'Sales & Distribution Staff',
    ]);
    $timestamp = now();
    DB::table('product_monitor_statuses')->insert([
        'product_id' => 'B-001',
        'status' => 'For Distribution',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    DB::table('product_monitor_alert_reads')->insert([
        'user_id' => $user->id,
        'alert_id' => 'expiration-B-001',
        'read_at' => $timestamp,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    $this->postJson(route('warehouse.auth.login'), [
        'email' => 'sales@example.com',
        'password' => 'warehouse-password',
    ])
        ->assertOk()
        ->assertJsonPath('workspaceState.productStatuses.B-001', 'For Distribution')
        ->assertJsonPath('workspaceState.readAlertIds.0', 'expiration-B-001');
});
