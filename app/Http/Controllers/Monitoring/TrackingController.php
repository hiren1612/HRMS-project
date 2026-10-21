<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Monitoring\ActivityLog;
use App\Models\Monitoring\IdlePeriod;
use App\Models\Monitoring\TrackingDevice;
use App\Models\Monitoring\TrackingSession;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The tracking list and the per-employee day view.
 *
 * Both are read-only. Screenshots and live viewing are deliberately not here;
 * they are separate capabilities on the permission row and get their own
 * screens in phase 6.
 */
class TrackingController extends Controller
{
    /** Rows per page on the list. */
    private const PER_PAGE = 20;

    /**
     * One row per employee for the chosen day, with their totals.
     */
    public function index(Request $request): View
    {
        $day = $this->resolveDay($request);
        $search = trim((string) $request->query('search', ''));
        $department = (string) $request->query('department', '');

        // Totals per employee for the day, built from the sessions rather
        // than the buckets: cheaper, and the session already carries the
        // reconciled figures the agent reported.
        $sessions = TrackingSession::query()
            ->with(['device:id,device_name,platform,last_seen_at'])
            ->forDay($day)
            ->get()
            ->groupBy('employee_id');

        $employees = Employee::query()
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            }))
            ->when($department !== '', fn ($q) => $q->where('department', $department))
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code', 'department', 'designation']);

        $rows = $employees
            ->map(function (Employee $employee) use ($sessions) {
                $own = $sessions->get($employee->id) ?? collect();

                $active = (int) $own->sum('active_seconds');
                $idle = (int) $own->sum('idle_seconds');
                $tracked = $active + $idle;

                return [
                    'employee' => $employee,
                    'sessions' => $own->count(),
                    'active_seconds' => $active,
                    'idle_seconds' => $idle,
                    'tracked_seconds' => $tracked,
                    'activity_percent' => $tracked > 0 ? (int) round($active / $tracked * 100) : 0,
                    'running' => $own->contains(fn (TrackingSession $s) => $s->isRunning()),
                    'first_started' => $own->min('started_at'),
                    'last_seen' => $own->max('ended_at') ?: $own->max('started_at'),
                ];
            })
            // Somebody who tracked nothing is still worth seeing, but below
            // the people who did.
            ->sortByDesc('tracked_seconds')
            ->values();

        return view('monitoring.index', [
            'day' => $day,
            'isToday' => $day->isSameDay(CarbonImmutable::today()),
            'rows' => $this->paginate($rows, $request),
            'departments' => Employee::query()
                ->whereNotNull('department')
                ->where('department', '!=', '')
                ->distinct()
                ->orderBy('department')
                ->pluck('department'),
            'totalTracked' => (int) $rows->sum('tracked_seconds'),
            'totalActive' => (int) $rows->sum('active_seconds'),
        ]);
    }

    /**
     * One employee's day: sessions, the activity timeline, and idle periods.
     */
    public function show(Request $request, Employee $employee): View
    {
        $day = $this->resolveDay($request);

        $sessions = TrackingSession::query()
            ->with('device:id,device_name,platform,agent_version,last_seen_at')
            ->where('employee_id', $employee->id)
            ->forDay($day)
            ->orderBy('started_at')
            ->get();

        $buckets = ActivityLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('bucket_started_at', [$day->startOfDay(), $day->endOfDay()])
            ->orderBy('bucket_started_at')
            ->get(['bucket_started_at', 'bucket_ended_at', 'active_seconds', 'idle_seconds', 'status']);

        $idlePeriods = IdlePeriod::query()
            ->whereIn('session_id', $sessions->pluck('id'))
            ->orderBy('started_at')
            ->get();

        $active = (int) $sessions->sum('active_seconds');
        $idle = (int) $sessions->sum('idle_seconds');
        $tracked = $active + $idle;

        return view('monitoring.show', [
            'employee' => $employee,
            'day' => $day,
            'isToday' => $day->isSameDay(CarbonImmutable::today()),
            'sessions' => $sessions,
            'idlePeriods' => $idlePeriods,
            'devices' => TrackingDevice::where('employee_id', $employee->id)
                ->orderByDesc('last_seen_at')
                ->get(),
            'totals' => [
                'active_seconds' => $active,
                'idle_seconds' => $idle,
                'tracked_seconds' => $tracked,
                'activity_percent' => $tracked > 0 ? (int) round($active / $tracked * 100) : 0,
                'sessions' => $sessions->count(),
                'longest_idle' => (int) $idlePeriods->max('seconds'),
            ],
            'timeline' => $this->timeline($buckets),
        ]);
    }

    /**
     * Collapse the per-minute buckets into one entry per hour so the day can
     * be read at a glance instead of as 480 rows.
     *
     * @param  \Illuminate\Support\Collection<int, ActivityLog>  $buckets
     * @return array<int, array<string, int>>
     */
    private function timeline(\Illuminate\Support\Collection $buckets): array
    {
        $hours = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $hours[$hour] = ['active' => 0, 'idle' => 0];
        }

        foreach ($buckets as $bucket) {
            $hour = (int) $bucket->bucket_started_at->format('G');

            $hours[$hour]['active'] += (int) $bucket->active_seconds;
            $hours[$hour]['idle'] += (int) $bucket->idle_seconds;
        }

        return $hours;
    }

    /**
     * Paginate an in-memory collection.
     *
     * The rows are assembled in PHP (an employee with no sessions still needs
     * a row), so the database cannot do the paging for us.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     */
    private function paginate(\Illuminate\Support\Collection $rows, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));

        return new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    /**
     * Same rule as the overview: unparseable or future dates fall back to
     * today rather than erroring.
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

        return $day->isAfter(CarbonImmutable::today()) ? CarbonImmutable::today() : $day;
    }
}
