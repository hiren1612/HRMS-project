<?php

namespace App\Http\Middleware;

use App\Models\Monitoring\TrackingDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns an agent's Sanctum token back into the device that owns it, and
 * decides whether that device is currently allowed to send data.
 *
 * The device is identified by the token's name, which is set to the device
 * UUID when the token is issued. That avoids a column on the devices table
 * pointing at a Sanctum row and a Sanctum row pointing back.
 *
 * Usage:
 *   ->middleware(ResolveTrackingDevice::class)            // device must exist
 *   ->middleware(ResolveTrackingDevice::class . ':approved')  // and be approved
 *
 * The distinction matters. A pending device must still be able to ask for
 * its settings — that is how the agent learns it is waiting for approval
 * and says so on screen — but it must not be able to post a single second
 * of activity or a single screenshot until somebody has approved it.
 */
class ResolveTrackingDevice
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $require = null): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user || ! $token) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        $employee = $user->employee;

        if (! $employee) {
            // A user account with no employee record has nothing to track
            // against; every table in the module hangs off employee_id.
            return response()->json([
                'message' => 'This account is not linked to an employee record.',
            ], Response::HTTP_FORBIDDEN);
        }

        $device = TrackingDevice::where('device_uuid', $token->name)
            ->where('employee_id', $employee->id)
            ->first();

        if (! $device) {
            // The token is valid but its device is gone — deleted by an
            // administrator, most likely. Tell the agent to forget the token
            // rather than leaving it retrying against nothing.
            return response()->json([
                'message' => 'This device is no longer registered.',
                'action' => 'sign_out',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($device->status === TrackingDevice::STATUS_REVOKED) {
            return response()->json([
                'message' => 'This device has been revoked.',
                'action' => 'sign_out',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($require === 'approved' && ! $device->isActive()) {
            return response()->json([
                'message' => 'This device is waiting for approval.',
                'device' => ['status' => $device->status],
            ], Response::HTTP_FORBIDDEN);
        }

        // Last contact, for the "stopped reporting" column on the dashboard.
        // Written on every authenticated call rather than only on heartbeat,
        // so a device that is uploading but not heartbeating still counts as
        // alive.
        $device->forceFill([
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
        ])->saveQuietly();

        $request->attributes->set('tracking_device', $device);
        $request->attributes->set('tracking_employee', $employee);

        return $next($request);
    }
}
