<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Monitoring\LiveSession;
use App\Models\Monitoring\MonitoringAuditLog;
use App\Services\Monitoring\TrackingSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Live screen viewing.
 *
 * The rule this file exists to enforce: no frame is accepted until the agent
 * has confirmed the on-screen indicator is actually visible to the employee.
 * Being watched without being able to tell is the thing the design is meant
 * to make impossible, so the check lives here on the server rather than only
 * in the agent, which runs on a machine the employee controls.
 *
 * The lifecycle is requested → notified → active → ended. A frame arriving
 * while the session is still only "requested" means the indicator was never
 * shown, and is refused.
 *
 * Frames are held in the cache, never written to disk. A live view is
 * something you watch now; keeping a recording of it is a much larger
 * decision than switching live viewing on.
 */
class LiveController extends Controller
{
    /** How long a pushed frame stays available to the viewer. */
    private const FRAME_TTL_SECONDS = 15;

    public function __construct(private readonly TrackingSettings $settings)
    {
    }

    /**
     * The agent reports that the indicator is now on screen.
     */
    public function notified(Request $request, int $liveSession): JsonResponse
    {
        $live = $this->ownedLiveSession($request, $liveSession);

        if (! $live) {
            return response()->json(['message' => 'Unknown live session.'], 404);
        }

        if ($this->expire($live)) {
            return response()->json(['message' => 'This request has expired.'], 410);
        }

        $live->update([
            'status' => LiveSession::STATUS_NOTIFIED,
            'employee_notified_at' => $live->employee_notified_at ?? now(),
        ]);

        MonitoringAuditLog::create([
            'user_id' => $request->user()->id,
            'employee_id' => $live->employee_id,
            'action' => 'live.indicator_confirmed',
            'subject_type' => LiveSession::class,
            'subject_id' => $live->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * One JPEG frame from the agent.
     */
    public function frame(Request $request, int $liveSession): JsonResponse
    {
        $request->validate([
            'frame' => ['required', 'file', 'mimetypes:image/jpeg', 'max:4096'],
        ]);

        $live = $this->ownedLiveSession($request, $liveSession);

        if (! $live) {
            return response()->json(['message' => 'Unknown live session.'], 404);
        }

        if (! $this->settings->liveEnabled()) {
            $this->close($live, LiveSession::END_BY_ADMIN);

            return response()->json([
                'message' => 'Live viewing is disabled.',
                'action' => 'stop_streaming',
            ], 403);
        }

        // Genuine expiry only. A session somebody deliberately ended is a
        // different answer from one that ran past its ceiling, and the agent
        // reads the two differently.
        if ($this->expire($live)) {
            return response()->json([
                'message' => 'This session has expired.',
                'action' => 'stop_streaming',
            ], 410);
        }

        if (! $live->isOpen()) {
            return response()->json([
                'message' => 'This live session has ended.',
                'action' => 'stop_streaming',
            ], 403);
        }

        // The consent gate. A session still sitting at "requested" means the
        // agent never confirmed the indicator, so there is no evidence the
        // employee can see they are being watched.
        $notice = $this->settings->requiresNotificationBeforeStreaming();

        if ($notice && $live->employee_notified_at === null) {
            return response()->json([
                'message' => 'The employee has not been shown the indicator yet.',
                'action' => 'stop_streaming',
            ], 403);
        }

        // First frame after the indicator went up promotes the session to
        // active. mayStream() only returns true from this point on, which is
        // what the viewer side checks before serving anything.
        if ($live->status === LiveSession::STATUS_NOTIFIED) {
            $live->update([
                'status' => LiveSession::STATUS_ACTIVE,
                'started_at' => $live->started_at ?? now(),
            ]);
        }

        if (! $live->mayStream($notice)) {
            return response()->json([
                'message' => 'This live session is not cleared to stream.',
                'action' => 'stop_streaming',
            ], 403);
        }

        // Deliberately the cache and not the disk. Nothing here is a record.
        Cache::put(
            $this->frameKey($live->id),
            base64_encode(file_get_contents($request->file('frame')->getRealPath())),
            self::FRAME_TTL_SECONDS,
        );

        return response()->json(['ok' => true]);
    }

    public function end(Request $request, int $liveSession): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:40'],
        ]);

        $live = $this->ownedLiveSession($request, $liveSession);

        if (! $live) {
            return response()->json(['message' => 'Unknown live session.'], 404);
        }

        $this->close($live, $data['reason'] ?? LiveSession::END_DISCONNECTED);

        return response()->json(['ok' => true]);
    }

    /**
     * End a session that has outlived the configured ceiling.
     *
     * @return bool  true when it was expired by this call or already over
     */
    private function expire(LiveSession $live): bool
    {
        $maxMinutes = (int) config('monitoring.live.max_duration_minutes', 30);

        if ($live->hasExpired($maxMinutes)) {
            $this->close($live, LiveSession::END_TIMEOUT);

            return true;
        }

        return false;
    }

    private function close(LiveSession $live, string $reason): void
    {
        if ($live->isOpen()) {
            $live->update([
                'status' => LiveSession::STATUS_ENDED,
                'ended_at' => now(),
                'end_reason' => $reason,
            ]);
        }

        Cache::forget($this->frameKey($live->id));
    }

    private function frameKey(int $liveSessionId): string
    {
        return "monitoring.live.frame.{$liveSessionId}";
    }

    /**
     * A live session belonging to the device making the request, so one
     * agent cannot push frames into another employee's session.
     */
    private function ownedLiveSession(Request $request, int $id): ?LiveSession
    {
        $device = $request->attributes->get('tracking_device');

        return LiveSession::where('id', $id)
            ->where('device_id', $device->id)
            ->first();
    }
}
