<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('password reset tokens can only be used once', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'first-new-password',
            'password_confirmation' => 'first-new-password',
        ])->assertSessionHasNoErrors();

        $this->from('/reset-password/'.$notification->token)
            ->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'second-new-password',
                'password_confirmation' => 'second-new-password',
            ])
            ->assertSessionHasErrors('email');

        expect(Hash::check('first-new-password', $user->fresh()->password))->toBeTrue();

        return true;
    });
});

test('password reset tokens expire after the configured period', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);
    $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->from('/reset-password/'.$notification->token)
            ->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'expired-link-password',
                'password_confirmation' => 'expired-link-password',
            ])
            ->assertSessionHasErrors('email');

        expect(Hash::check('password', $user->fresh()->password))->toBeTrue();

        return true;
    });
});
