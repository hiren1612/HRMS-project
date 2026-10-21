<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Monitoring\LiveSession;
use App\Models\Monitoring\TrackingDevice;
use App\Models\Monitoring\TrackingSession;
use App\Services\Monitoring\TrackingSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings, session lifecycle and the heartbeat.
 *
 * The heartbeat is also the channel the server uses to tell the agent to do
 * something — currently only "start streaming your screen". The agent polls;
 * the server never connects to the employee's machine.
 */
class SessionController extends Controller
{
    public function __construct(private readonly TrackingSettings $settings)
    {
    }

    /**
     * Who am I, is this device approved, and what are my settings?
     *
     * Deliberately reachable by a pending device: this is how the agent
     * finds out it is waiting for approval and says so rather than sitting
     * there looking broken.
     */
    public function show(Request $request): JsonResponse
    {
        $device = $request->attributes->get('tracking_device');
        $employee = $request->attributes->get('tracking_employee');
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'employee_code' => $employee->employee_code,
                'designation' => $employee->designation,
            ],
            'device' => [
                'status' => $device->status,
                'requires_approval' => $this->settings->requiresDeviceApproval(),
            ],
            'settings' => $this->settings->agentPayload(),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'started_at' => ['nullable', 'date'],
        ]);

        $device = $request->attributes->get('tracking_device');
        $employee = $request->attributes->get('tracking_employee');

        // An agent that crashed and restarted will ask for a new session
        // while its old one is still marked running. Reuse it rather than
        // leaving an orphan open forever and double-counting the day.
        $existing = TrackingSession::where('device_id', $device->id)
            ->where('status', TrackingSession::STATUS_RUNNING)
            ->latest('started_at')
            ->first();

        if ($existing) {
            return response()->json(['session_id' => $existing->id, 'resumed' => true], 200);
        }

        $session = TrackingSession::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'started_at' => $request->date('started_at') ?? now(),
            'active_seconds' => 0,
            'idle_seconds' => 0,
            'status' => TrackingSession::STATUS_RUNNING,
        ]);

        return response()->json(['session_id' => $session->id], 201);
    }

    public function end(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'integer'],
            'ended_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:30'],
        ]);

        $session = $this->ownedSession($request, $data['session_id']);

        if (! $session) {
            return response()->json(['message' => 'Unknown session.'], 404);
        }

        // Ending an already-ended session is not an error: the agent retries
        // after a dropped connection and must not be left looping.
        if ($session->status === TrackingSession::STATUS_RUNNING) {
            $session->update([
                'ended_at' => $request->date('ended_at') ?? now(),
                'status' => TrackingSession::STATUS_ENDED,
                'ended_reason' => $data['reason'] ?? TrackingSession::ENDED_MANUAL,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Keep-alive, and the agent's only chance to be told to do something.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['nullable', 'integer'],
            'status' => ['required', 'string', 'max:20'],
            'active_seconds' => ['required', 'integer', 'min:0'],
            'idle_seconds' => ['required', 'integer', 'min:0'],
            'at' => ['nullable', 'date'],
        ]);

        $device = $request->attributes->get('tracking_device');
        $employee = $request->attributes->get('tracking_employee');

        if (! empty($data['session_id'])) {
            $session = $this->ownedSession($request, $data['session_id']);

            // The totals the agent reports are authoritative for the running
            // session: it is the only thing that knows what happened while
            // the network was down.
            $session?->update([
                'active_seconds' => $data['active_seconds'],
                'idle_seconds' => $data['idle_seconds'],
            ]);
        }

        return response()->json([
            'ok' => true,
            'live' => $this->pendingLiveInstruction($employee->id, $device->id),
            // Handing settings back on every heartbeat means a changed
            // threshold or a switched-off module takes effect within a
            // minute, without the employee restarting anything.
            'settings' => $this->settings->agentPayload(),
        ]);
    }

    /**
     * A live-screen request waiting for this device, if there is one.
     *
     * @return array<string, mixed>|null
     */
    private function pendingLiveInstruction(int $employeeId, int $deviceId): ?array
    {
        if (! $this->settings->liveEnabled()) {
            return null;
        }

        $live = LiveSession::query()
            ->where('employee_id', $employeeId)
            ->where('device_id', $deviceId)
            ->where('status', LiveSession::STATUS_REQUESTED)
            ->latest()
            ->first();

        if (! $live || $live->hasExpired((int) config('monitoring.live.max_duration_minutes', 30))) {
            return null;
        }

        return [
            'id' => $live->id,
            'requires_notice' => $this->settings->requiresNotificationBeforeStreaming(),
        ];
    }

    /**
     * A session belonging to this device. Guards against one agent posting
     * into another employee's session by guessing an id.
     */
    private function ownedSession(Request $request, int $sessionId): ?TrackingSession
    {
        $device = $request->attributes->get('tracking_device');

        return TrackingSession::where('id', $sessionId)
            ->where('device_id', $device->id)
            ->first();
    }
}
