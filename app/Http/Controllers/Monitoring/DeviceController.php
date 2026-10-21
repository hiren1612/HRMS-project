<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\Monitoring\MonitoringAuditLog;
use App\Models\Monitoring\TrackingDevice;
use App\Models\Monitoring\TrackingSession;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Device approval.
 *
 * A device that is not approved can read its settings and nothing else, so
 * this queue is what actually lets an agent start reporting. Before this
 * screen existed the only way through it was an UPDATE statement.
 *
 * Revoking is the other half and matters more: it is how a lost or stolen
 * laptop is stopped, so it takes effect on the device's very next request
 * rather than whenever somebody remembers to uninstall the agent.
 */
class DeviceController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');

        $devices = TrackingDevice::query()
            ->with('employee:id,name,employee_code,department', 'approver:id,name')
            ->when(
                in_array($status, TrackingDevice::STATUSES, true),
                fn ($q) => $q->where('status', $status),
            )
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->orderByDesc('last_seen_at')
            ->paginate(25)
            ->withQueryString();

        return view('monitoring.devices', [
            'devices' => $devices,
            'status' => $status,
            'counts' => [
                'pending' => TrackingDevice::where('status', TrackingDevice::STATUS_PENDING)->count(),
                'approved' => TrackingDevice::where('status', TrackingDevice::STATUS_APPROVED)->count(),
                'revoked' => TrackingDevice::where('status', TrackingDevice::STATUS_REVOKED)->count(),
            ],
        ]);
    }

    public function approve(Request $request, TrackingDevice $device): RedirectResponse
    {
        if ($device->status === TrackingDevice::STATUS_APPROVED) {
            return back()->with('success', 'That device was already approved.');
        }

        $device->update([
            'status' => TrackingDevice::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        MonitoringAuditLog::record(
            MonitoringAuditLog::DEVICE_APPROVED,
            $device,
            $device->employee_id,
            ['device_name' => $device->device_name, 'device_uuid' => $device->device_uuid],
        );

        return back()->with('success', "Approved {$device->device_name}.");
    }

    public function revoke(Request $request, TrackingDevice $device): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $device->update(['status' => TrackingDevice::STATUS_REVOKED]);

        // Kill the agent's token as well as flipping the flag. Without this
        // the device keeps a working credential until it happens to make a
        // request, which is the wrong behaviour for a stolen machine.
        $device->employee?->user?->tokens()
            ->where('name', $device->device_uuid)
            ->delete();

        // An open session left running would sit on the dashboard as
        // somebody still at their desk.
        TrackingSession::where('device_id', $device->id)
            ->where('status', TrackingSession::STATUS_RUNNING)
            ->update([
                'status' => TrackingSession::STATUS_ENDED,
                'ended_at' => now(),
                'ended_reason' => TrackingSession::ENDED_STALE,
            ]);

        MonitoringAuditLog::record(
            MonitoringAuditLog::DEVICE_REVOKED,
            $device,
            $device->employee_id,
            [
                'device_name' => $device->device_name,
                'device_uuid' => $device->device_uuid,
                'reason' => $data['reason'] ?? null,
            ],
        );

        return back()->with('success', "Revoked {$device->device_name}. Its token has been destroyed.");
    }
}
