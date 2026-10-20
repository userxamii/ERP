<?php

namespace App\Providers;

use App\Models\SystemActivity;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                SystemActivity::query()->create([
                    'user_id' => $event->user->id,
                    'event_type' => 'login_succeeded',
                    'description' => 'User signed in.',
                    'ip_address' => request()->ip(),
                ]);
            }
        });

        Event::listen(Failed::class, function (Failed $event): void {
            SystemActivity::query()->create([
                'user_id' => $event->user instanceof User ? $event->user->id : null,
                'event_type' => 'login_failed',
                'description' => 'Failed sign-in attempt.',
                'ip_address' => request()->ip(),
                'metadata' => ['email' => request()->input('email')],
            ]);
        });

        Event::listen(Lockout::class, function (Lockout $event): void {
            SystemActivity::query()->create([
                'event_type' => 'login_locked',
                'description' => 'Sign-in temporarily locked after repeated attempts.',
                'ip_address' => $event->request->ip(),
                'metadata' => ['email' => $event->request->input('email')],
            ]);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                SystemActivity::query()->create([
                    'user_id' => $event->user->id,
                    'event_type' => 'logout',
                    'description' => 'User signed out.',
                    'ip_address' => request()->ip(),
                ]);
            }
        });
    }
}
