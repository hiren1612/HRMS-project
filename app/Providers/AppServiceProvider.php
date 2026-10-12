<?php

namespace App\Providers;

use App\Models\Activity;
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
        // Sign-in and sign-out are part of the audit trail (point #4).
        Event::listen(Login::class, function (Login $event) {
            Activity::log('auth.login', $event->user->name . ' signed in');
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                Activity::log('auth.logout', $event->user->name . ' signed out');
            }
        });
    }
}
