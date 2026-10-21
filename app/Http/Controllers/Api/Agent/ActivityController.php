<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Monitoring\ActivityLog;
use App\Models\Monitoring\IdlePeriod;
use App\Models\Monitoring\TrackingSession;
use App\Services\Monitoring\TrackingSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Receives batches of per-minute activity buckets and idle periods.
 *
 * This is the endpoint that has to survive a bad network. The agent queues
 * everything to disk and re-sends until the server confirms, so the same
 * batch arriving twice is normal operation, not an attack. Every write here
 * is idempotent on the client's own identifier.
 */
class ActivityController extends Controller
{
    /** Cap on one batch, so a broken agent cannot post a million rows. */
    private const MAX_BUCKETS = 500;

    private const MAX_IDLE_PERIODS = 200;

    public function __construct(private readonly TrackingSettings $settings)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'integer'],

            'buckets' => ['array', 'max:' . self::MAX_BUCKETS],
            'buckets.*.client_uuid' => ['required', 'string', 'max:64'],
            'buckets.*.bucket_started_at' => ['required', 'date'],
            'buckets.*.bucket_ended_at' => ['required', 'date'],
            'buckets.*.active_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
            'buckets.*.idle_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
            'buckets.*.mouse_events' => ['nullable', 'integer', 'min:0'],
            'buckets.*.key_events' => ['nullable', 'integer', 'min:0'],
            'buckets.*.status' => ['required', 'string', 'max:20'],

            'idle_periods' => ['array', 'max:' . self::MAX_IDLE_PERIODS],
            'idle_periods.*.startedAt' => ['required', 'date'],
            'idle_periods.*.endedAt' => ['nullable', 'date'],
            'idle_periods.*.seconds' => ['required', 'integer', 'min:0'],
        ]);

        $device = $request->attributes->get('tracking_device');
        $employee = $request->attributes->get('tracking_employee');

        $session = TrackingSession::where('id', $data['session_id'])
            ->where('device_id', $device->id)
            ->first();

        if (! $session) {
            return response()->json(['message' => 'Unknown session.'], 404);
        }

        $buckets = $data['buckets'] ?? [];
        $idlePeriods = $data['idle_periods'] ?? [];

        $accepted = 0;
        $skipped = 0;

        DB::transaction(function () use ($buckets, $idlePeriods, $session, $employee, &$accepted, &$skipped) {
            foreach ($buckets as $bucket) {
                // Outside working hours the bucket is acknowledged and
                // dropped, not banked. The agent is told it landed so it
                // stops retrying; the time simply does not count.
                if (! $this->settings->withinWorkingHours(\Illuminate\Support\Carbon::parse($bucket['bucket_started_at']))) {
                    $skipped++;
                    $accepted++;
                    continue;
                }

                // updateOrCreate on the client's uuid is what makes a
                // re-send harmless: the second arrival overwrites the first
                // with identical values instead of adding a duplicate.
                ActivityLog::updateOrCreate(
                    ['client_uuid' => $bucket['client_uuid']],
                    [
                        'session_id' => $session->id,
                        'employee_id' => $employee->id,
                        'bucket_started_at' => $bucket['bucket_started_at'],
                        'bucket_ended_at' => $bucket['bucket_ended_at'],
                        'active_seconds' => $bucket['active_seconds'],
                        'idle_seconds' => $bucket['idle_seconds'],
                        'mouse_events' => $bucket['mouse_events'] ?? 0,
                        'key_events' => $bucket['key_events'] ?? 0,
                        'status' => $bucket['status'],
                    ],
                );

                $accepted++;
            }

            foreach ($idlePeriods as $period) {
                // Idle periods carry no client uuid, so they are matched on
                // the session plus their start instant, which is unique for
                // one device: you cannot begin two idle stretches at once.
                IdlePeriod::updateOrCreate(
                    [
                        'session_id' => $session->id,
                        'started_at' => $period['startedAt'],
                    ],
                    [
                        'employee_id' => $employee->id,
                        'ended_at' => $period['endedAt'] ?? null,
                        'seconds' => $period['seconds'],
                    ],
                );

                $accepted++;
            }

            // Keep the session's totals in step with the buckets that were
            // actually stored, so the dashboard and the detail timeline
            // cannot disagree.
            $session->refreshTotalsFromLogs();
        });

        return response()->json([
            'accepted' => $accepted,
            'skipped_outside_hours' => $skipped,
        ]);
    }
}
