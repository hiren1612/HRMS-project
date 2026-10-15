<?php

namespace App\Providers;

use App\Models\Activity;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Carbon;
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
        $this->registerDateMacros();

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

    /**
     * QA U6: one date format across the whole application.
     *
     * Dates were written three ways (18 Nov 2026, 18/11/2026, 18 Nov, 2026)
     * depending on which screen you were on. Views call ->asDate() and
     * ->asDateTime() instead of format(), so the format is defined once in
     * config/hrms.php and changing it changes every screen.
     */
    private function registerDateMacros(): void
    {
        Carbon::macro('asDate', fn () => $this->format(config('hrms.date_format', 'd M Y')));
        Carbon::macro('asTime', fn () => $this->format(config('hrms.time_format', 'h:i A')));
        Carbon::macro('asDateTime', fn () => $this->format(config('hrms.datetime_format', 'd M Y, h:i A')));

        // "18 Nov 2026" is noise when the year is obvious; "18 Nov" is not a
        // different format, it is the same one with the year dropped.
        Carbon::macro('asShortDate', fn () => $this->format('d M'));

        // Same date format, prefixed with the weekday, for anything where the
        // day of the week is the point (meetings, today's attendance header).
        Carbon::macro('asDateWithDay', fn () => $this->format('D, ') . $this->asDate());
        Carbon::macro('asDateWithFullDay', fn () => $this->format('l, ') . $this->asDate());
    }
}
