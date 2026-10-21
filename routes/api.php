<?php

use App\Http\Controllers\Api\Agent\ActivityController;
use App\Http\Controllers\Api\Agent\AuthController;
use App\Http\Controllers\Api\Agent\LiveController;
use App\Http\Controllers\Api\Agent\ScreenshotController;
use App\Http\Controllers\Api\Agent\SessionController;
use App\Http\Middleware\ResolveTrackingDevice;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Desktop tracking agent
|--------------------------------------------------------------------------
|
| The only API surface this application has. Every route is for the Windows
| agent; nothing here is called by the browser, which continues to use the
| session guard exactly as it did before Sanctum was installed.
|
| Two tiers of access:
|
|   ResolveTrackingDevice              the device exists and is not revoked
|   ResolveTrackingDevice:approved     and has been approved
|
| Settings and sign-out sit in the first tier on purpose. A device waiting
| for approval still needs to be told that is what it is waiting for, or the
| agent just looks broken to the employee. Everything that writes tracking
| data sits in the second.
|
| Rate limits are per device rather than per IP: a whole office behind one
| NAT address is normal, and one agent in a retry loop should not be able to
| lock out its colleagues.
|
*/

Route::prefix('agent')->name('api.agent.')->group(function () {

    // Unauthenticated. Throttled hard — this is the one endpoint that takes
    // a password, so it is the one worth brute-forcing.
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');

    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {

        // Tier one: reachable while pending approval.
        Route::middleware(ResolveTrackingDevice::class)->group(function () {
            Route::get('session', [SessionController::class, 'show'])->name('session');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        });

        // Tier two: approved devices only.
        Route::middleware(ResolveTrackingDevice::class . ':approved')->group(function () {
            Route::post('sessions/start', [SessionController::class, 'start'])->name('sessions.start');
            Route::post('sessions/end', [SessionController::class, 'end'])->name('sessions.end');
            Route::post('heartbeat', [SessionController::class, 'heartbeat'])->name('heartbeat');
            Route::post('activity', [ActivityController::class, 'store'])->name('activity');

            // Uploads get their own, looser limit: a backlog flushing after
            // a long offline spell is a burst of legitimate traffic.
            Route::post('screenshots', [ScreenshotController::class, 'store'])
                ->middleware('throttle:60,1')
                ->name('screenshots');

            Route::post('live/{liveSession}/notified', [LiveController::class, 'notified'])->name('live.notified');
            Route::post('live/{liveSession}/end', [LiveController::class, 'end'])->name('live.end');

            // Frames arrive about twice a second while somebody is watching.
            Route::post('live/{liveSession}/frame', [LiveController::class, 'frame'])
                ->middleware('throttle:240,1')
                ->name('live.frame');
        });
    });
});
