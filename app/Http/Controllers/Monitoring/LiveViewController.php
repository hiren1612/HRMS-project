<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Monitoring\LiveSession;
use App\Models\Monitoring\MonitoringAuditLog;
use App\Models\Monitoring\TrackingDevice;
use App\Services\Monitoring\TrackingSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The viewer's half of live screen sharing.
 *
 * The agent's half is already built and already refuses to send a frame
 * until it has confirmed the on-screen indicator is visible. This side
 * requests a session, polls for frames, and ends it.
 *
 * Nothing here can make a frame appear. If the agent is offline, or the
 * employee's machine never shows the indicator, the viewer waits and sees
 * nothing — which is the correct outcome, not a bug to work around.
 */
class LiveViewController extends Controller
{
    public function __construct(private readonly TrackingSettings $settings)
    {
    }

    /**
     * Pick somebody to watch, or watch them.
     */
    public function index(Request $request): View
    {
        $employeeId = (int) $request->query('employee', 0);
        $employee = $employeeId > 0 ? Employee::find($employeeId) : null;

        $session = null;

        if ($employee) {
            $session = LiveSession::where('employee_id', $employee->id)
                ->whereIn('status', [
                    LiveSession::STATUS_REQUESTED,
                    LiveSession::STATUS_NOTIFIED,
                    LiveSession::STATUS_ACTIVE,
                ])
                ->latest()
                ->first();
        }

        return view('monitoring.live', [
            'employee' => $employee,
            'session' => $session,
            'liveEnabled' => $this->settings->liveEnabled(),
            'maxMinutes' => (int) config('monitoring.live.max_duration_minutes', 30),

            // Only employees with an approved device can be watched at all.
            'candidates' => Employee::whereIn(
                'id',
                TrackingDevice::approved()->select('employee_id'),
            )->orderBy('name')->get(['id', 'name', 'employee_code', 'department']),
        ]);
    }

    /**
     * Ask an employee's agent to start streaming.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ]);

        if (! $this->settings->liveEnabled()) {
            return back()->withErrors(['employee_id' => 'Live viewing is switched off in monitoring settings.']);
        }

        $device = TrackingDevice::approved()
            ->where('employee_id', $data['employee_id'])
            ->orderByDesc('last_seen_at')
            ->first();

        if (! $device) {
            return back()->withErrors(['employee_id' => 'That employee has no approved device.']);
        }

        // Asking twice while one is already pending would leave two sessions
        // racing for the same agent.
        $existing = LiveSession::where('device_id', $device->id)
            ->whereIn('status', [LiveSession::STATUS_REQUESTED, LiveSession::STATUS_NOTIFIED, LiveSession::STATUS_ACTIVE])
            ->first();

        if ($existing) {
            return redirect()->route('monitoring.live', ['employee' => $data['employee_id']]);
        }

        $session = LiveSession::create([
            'employee_id' => $data['employee_id'],
            'device_id' => $device->id,
            'initiated_by' => $request->user()->id,
            'requested_at' => now(),
            'status' => LiveSession::STATUS_REQUESTED,
            'initiator_ip' => $request->ip(),
        ]);

        MonitoringAuditLog::record(
            MonitoringAuditLog::LIVE_REQUESTED,
            $session,
            $session->employee_id,
            ['device_name' => $device->device_name],
        );

        return redirect()->route('monitoring.live', ['employee' => $data['employee_id']]);
    }

    /**
     * The newest frame, as JSON, for the page to poll.
     *
     * Returns the session's state as well as the image, so the page can say
     * "waiting for the indicator" rather than showing an empty box.
     */
    public function frame(Request $request, LiveSession $session): JsonResponse
    {
        if ($session->hasExpired((int) config('monitoring.live.max_duration_minutes', 30))) {
            $session->update([
                'status' => LiveSession::STATUS_ENDED,
                'ended_at' => now(),
                'end_reason' => LiveSession::END_TIMEOUT,
            ]);
        }

        $frame = Cache::get("monitoring.live.frame.{$session->id}");

        return response()->json([
            'status' => $session->status,
            'open' => $session->isOpen(),
            'notified' => $session->employee_notified_at !== null,
            // A null frame on an open session means the agent has not sent
            // one yet, which is different from the session being over.
            'frame' => $frame,
            'seconds' => $session->durationSeconds(),
        ]);
    }

    public function destroy(Request $request, LiveSession $session): RedirectResponse
    {
        if ($session->isOpen()) {
            $session->update([
                'status' => LiveSession::STATUS_ENDED,
                'ended_at' => now(),
                'end_reason' => LiveSession::END_BY_ADMIN,
            ]);

            MonitoringAuditLog::record(
                MonitoringAuditLog::LIVE_ENDED,
                $session,
                $session->employee_id,
                ['ended_by' => 'viewer'],
            );
        }

        Cache::forget("monitoring.live.frame.{$session->id}");

        return redirect()->route('monitoring.live', ['employee' => $session->employee_id]);
    }
}
