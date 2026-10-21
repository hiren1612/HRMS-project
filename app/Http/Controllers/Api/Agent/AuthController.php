<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Monitoring\MonitoringAuditLog;
use App\Models\Monitoring\TrackingDevice;
use App\Models\User;
use App\Services\Monitoring\TrackingSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Sign-in and sign-out for the desktop agent.
 *
 * The employee types their ordinary HRMS password once, here. What goes back
 * is a Sanctum token bound to one device; the password is never stored by
 * the agent and never sent again.
 */
class AuthController extends Controller
{
    public function __construct(private readonly TrackingSettings $settings)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_uuid' => ['required', 'string', 'max:64'],
            'device_name' => ['required', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:120'],
            'agent_version' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            // Same message either way: a different one for "no such account"
            // tells an attacker which addresses are real.
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $employee = $user->employee;

        if (! $employee) {
            return response()->json([
                'message' => 'This account is not linked to an employee record, so it cannot be tracked.',
            ], 403);
        }

        $device = TrackingDevice::firstOrNew([
            'device_uuid' => $data['device_uuid'],
        ]);

        // A device UUID that already belongs to somebody else is not a
        // re-registration, it is two accounts on one machine. Refuse rather
        // than silently moving the device — and the history with it.
        if ($device->exists && $device->employee_id !== $employee->id) {
            return response()->json([
                'message' => 'This device is already registered to another employee.',
            ], 409);
        }

        $isNew = ! $device->exists;

        $device->fill([
            'employee_id' => $employee->id,
            'device_name' => $data['device_name'],
            'platform' => $data['platform'] ?? null,
            'agent_version' => $data['agent_version'] ?? null,
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
        ]);

        if ($isNew) {
            // Approval is the default posture. Where the setting says
            // otherwise, a new device starts approved so a rollout does not
            // need somebody clicking approve several hundred times.
            $device->status = $this->settings->requiresDeviceApproval()
                ? TrackingDevice::STATUS_PENDING
                : TrackingDevice::STATUS_APPROVED;
        }

        // Signing in again on a revoked device must not quietly un-revoke it.
        if ($device->status === TrackingDevice::STATUS_REVOKED) {
            return response()->json([
                'message' => 'This device has been revoked. Contact your administrator.',
            ], 403);
        }

        $device->save();

        // One live token per device: issuing a new one retires the old, so a
        // stolen laptop's token stops working the moment the employee signs
        // in somewhere else.
        $user->tokens()->where('name', $device->device_uuid)->delete();

        $token = $user->createToken($device->device_uuid, ['agent'])->plainTextToken;

        MonitoringAuditLog::create([
            'user_id' => $user->id,
            'employee_id' => $employee->id,
            'action' => $isNew ? 'device.registered' : 'device.signed_in',
            'subject_type' => TrackingDevice::class,
            'subject_id' => $device->id,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'context' => [
                'device_name' => $device->device_name,
                'platform' => $device->platform,
                'agent_version' => $device->agent_version,
            ],
        ]);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee_code' => $employee->employee_code,
                'designation' => $employee->designation,
            ],
            'device' => [
                'status' => $device->status,
                'requires_approval' => $this->settings->requiresDeviceApproval(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }
}
