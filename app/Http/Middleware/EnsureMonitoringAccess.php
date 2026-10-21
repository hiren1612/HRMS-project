<?php

namespace App\Http\Middleware;

use App\Models\Monitoring\MonitoringPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the productivity monitoring screens.
 *
 * Deliberately not a role check. Being an administrator of the HRMS is a
 * different thing from being cleared to look at what a colleague had on
 * screen, and the four existing roles cannot express that difference. Access
 * comes from a row in employee_monitoring_permissions; no row means no
 * access, including for administrators.
 *
 * Usage in routes:
 *   ->middleware(EnsureMonitoringAccess::class)                  // any access
 *   ->middleware(EnsureMonitoringAccess::class . ':screenshots') // a capability
 *
 * Capabilities map to the boolean columns on the permission row:
 *   screenshots, live, devices, settings
 */
class EnsureMonitoringAccess
{
    /**
     * Capability name as used in routes => the column that grants it.
     *
     * @var array<string, string>
     */
    private const CAPABILITIES = [
        'screenshots' => 'can_view_screenshots',
        'live' => 'can_view_live_screen',
        'devices' => 'can_manage_devices',
        'settings' => 'can_manage_settings',
    ];

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $capability = null): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $permission = MonitoringPermission::where('user_id', $user->id)->first();

        if (! $permission) {
            abort(Response::HTTP_FORBIDDEN, 'You have not been granted access to monitoring data.');
        }

        if ($capability !== null) {
            $column = self::CAPABILITIES[$capability]
                ?? abort(Response::HTTP_INTERNAL_SERVER_ERROR, "Unknown monitoring capability [{$capability}].");

            if (! $permission->{$column}) {
                abort(Response::HTTP_FORBIDDEN, 'You have not been granted that monitoring capability.');
            }
        }

        // Hand the row to the rest of the request so controllers and views can
        // hide what the viewer may not use, rather than offering a link that
        // only fails once clicked.
        $request->attributes->set('monitoring_permission', $permission);

        return $next($request);
    }
}
