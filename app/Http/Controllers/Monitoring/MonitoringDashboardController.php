<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Monitoring\ActivityLog;
use App\Models\Monitoring\TrackingDevice;
use App\Models\Monitoring\TrackingSession;
use App\Services\Monitoring\TrackingSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The productivity overview: who is working right now, and how the day is
 * going across the company.
 *
 * Every figure here is read-only and derived from what agents have already
 * reported. Nothing on this screen starts, stops or changes tracking.
 */
class MonitoringDashboardController extends Controller
{
    /**
     * A session still marked running but silent for this long is shown
     * separately rather than counted as somebody at their desk. The agent
     * heartbeats far more often than this, so silence means a crash, a
     * sleeping laptop or a lost network, none of which is "working".
     */
    private const STALE_AFTER_MINUTES = 10;

    public function __construct(private readonly TrackingSettings $settings)
    {
    }

    public function index(Request $request): View
    {
        $day = $this->resolveDay($request);

        $sessions = TrackingSession::query()
            ->with(['employee:id,name,employee_code,department', 'device:id,device_name,platform,last_seen_at'])
            ->forDay($day)
            ->get();

        // "Working now" is a live question, so it is answered from the open
        // sessions rather than from the day's totals.
        //
        // isStale() hits the database once per session, so it is only asked
        // of the running ones. On a normal day that is the number of people
        // currently signed in, not the whole company.
        $running = $sessions->filter(fn (TrackingSession $s) => $s->isRunning());

        return view('monitoring.overview', [
            'day' => $day,
            'isToday' => $day->isSameDay(CarbonImmutable::today()),
            'trackingEnabled' => $this->settings->isEnabled(),

            'workingNow' => $running->reject(fn (TrackingSession $s) => $s->isStale(self::STALE_AFTER_MINUTES))->values(),
            'staleNow' => $running->filter(fn (TrackingSession $s) => $s->isStale(self::STALE_AFTER_MINUTES))->values(),

            'totals' => $this->dayTotals($sessions),
            'topActive' => $this->topBy($sessions, 'active_seconds'),
            'mostIdle' => $this->topBy($sessions, 'idle_seconds'),
            'hourly' => $this->hourlyActivity($day),

            'pendingDevices' => TrackingDevice::with('employee:id,name')
                ->where('status', 'pending')
                ->latest()
                ->limit(10)
                ->get(),
            'pendingDeviceCount' => TrackingDevice::where('status', 'pending')->count(),

            'notReporting' => $this->notReporting($sessions),
        ]);
    }

    /**
     * The date being viewed. Anything unparseable falls back to today rather
     * than erroring: a mistyped URL should not be a stack trace.
     */
    private function resolveDay(Request $request): CarbonImmutable
    {
        $raw = $request->query('day');

        if (! is_string($raw) || $raw === '') {
            return CarbonImmutable::today();
        }

        try {
            $day = CarbonImmutable::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }

        // No future days; there is nothing to show and it only confuses.
        return $day->isAfter(CarbonImmutable::today()) ? CarbonImmutable::today() : $day;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TrackingSession>  $sessions
     * @return array<string, int|float>
     */
    private function dayTotals(\Illuminate\Support\Collection $sessions): array
    {
        $active = (int) $sessions->sum('active_seconds');
        $idle = (int) $sessions->sum('idle_seconds');
        $tracked = $active + $idle;

        return [
            'employees' => $sessions->pluck('employee_id')->unique()->count(),
            'sessions' => $sessions->count(),
            'active_seconds' => $active,
            'idle_seconds' => $idle,
            'tracked_seconds' => $tracked,
            // Guard the divide: a day with no data is 0%, not a division error.
            'activity_percent' => $tracked > 0 ? round($active / $tracked * 100) : 0,
        ];
    }

    /**
     * Employees ranked by one of the second columns, highest first.
     *
     * Sessions are summed per employee, because somebody who signed out at
     * lunch and back in afterwards has two rows for one day's work.
     *
     * @param  \Illuminate\Support\Collection<int, TrackingSession>  $sessions
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function topBy(\Illuminate\Support\Collection $sessions, string $column, int $limit = 5): \Illuminate\Support\Collection
    {
        return $sessions
            ->groupBy('employee_id')
            ->map(fn ($group) => [
                'employee' => $group->first()->employee,
                'active_seconds' => (int) $group->sum('active_seconds'),
                'idle_seconds' => (int) $group->sum('idle_seconds'),
            ])
            ->sortByDesc($column)
            ->take($limit)
            ->values();
    }

    /**
     * Active seconds per hour of the day, for the shape of the working day.
     *
     * Read from the activity buckets rather than the sessions, because a
     * session spanning nine hours says nothing about when the work happened.
     *
     * @return array<int, int>  hour (0-23) => active seconds
     */
    private function hourlyActivity(CarbonImmutable $day): array
    {
        // Extracting the hour is one of the few things SQL engines spell
        // differently, and the suite runs on SQLite while production is
        // MySQL. Grouping in PHP instead would mean pulling a whole company's
        // worth of per-minute buckets into memory, so branch on the driver.
        $hour = match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%H', bucket_started_at) AS INTEGER)",
            'pgsql' => 'EXTRACT(HOUR FROM bucket_started_at)',
            'sqlsrv' => 'DATEPART(HOUR, bucket_started_at)',
            default => 'HOUR(bucket_started_at)',
        };

        $rows = ActivityLog::query()
            ->selectRaw("{$hour} AS hour, SUM(active_seconds) AS seconds")
            ->whereBetween('bucket_started_at', [$day->startOfDay(), $day->endOfDay()])
            ->groupBy(DB::raw($hour))
            ->pluck('seconds', 'hour');

        // Fill the gaps so the chart has a bar for every hour, including the
        // empty ones. A missing hour and a zero hour look different and mean
        // the same thing here.
        $hours = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $hours[$hour] = (int) ($rows[$hour] ?? 0);
        }

        return $hours;
    }

    /**
     * Employees with an approved device who reported nothing on this day.
     *
     * This is the figure worth acting on: tracking that silently stops is
     * indistinguishable from an employee not working unless somebody checks.
     *
     * @param  \Illuminate\Support\Collection<int, TrackingSession>  $sessions
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    private function notReporting(\Illuminate\Support\Collection $sessions): \Illuminate\Support\Collection
    {
        $reported = $sessions->pluck('employee_id')->unique();

        $withDevices = TrackingDevice::approved()
            ->pluck('employee_id')
            ->unique()
            ->diff($reported);

        if ($withDevices->isEmpty()) {
            return collect();
        }

        return Employee::whereIn('id', $withDevices)
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code', 'department']);
    }
}
