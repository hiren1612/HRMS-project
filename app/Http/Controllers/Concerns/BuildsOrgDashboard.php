<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Activity;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared organisation-wide dashboard data used by both the Admin Dashboard
 * and the HR Dashboard.
 */
trait BuildsOrgDashboard
{
    /**
     * Employees whose first clock-in today is after this time (24h HH:MM)
     * are counted as "Late Arrival"; on or before it, "On Time".
     */
    protected const LATE_AFTER = '10:00';

    /**
     * Probation length in months. There is no probation column, so the review
     * date is derived from the employee's hire_date.
     */
    protected const PROBATION_MONTHS = 6;

    /** Days either side of the probation date within which a review counts as due. */
    protected const PROBATION_WINDOW_DAYS = 30;

    /**
     * Build the organisation overview: headcount/attendance stats, today's
     * attendance breakdown, department/designation breakdowns, pending leaves,
     * announcements and holidays.
     *
     * @return array<string, mixed>
     */
    protected function orgDashboard(): array
    {
        $totalEmployees = Employee::count();

        // Present = employees with any attendance record today.
        $presentToday = Employee::whereHas('attendances', fn ($q) => $q->whereDate('date', today()))->count();

        // On approved leave covering today.
        $onLeaveToday = Leave::where('status', Leave::STATUS_APPROVED)
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->distinct('employee_id')
            ->count('employee_id');

        // Today's attendance split into On Time / Late Arrival / Absent for the
        // "Employee Attendance" donut. On Time vs Late is based on each
        // employee's FIRST clock-in against the LATE_AFTER office-start time.
        $todayClockIns = Attendance::whereDate('date', today())
            ->whereNotNull('clock_in')
            ->orderBy('clock_in')
            ->get(['employee_id', 'clock_in'])
            ->groupBy('employee_id');

        $onTime = 0;
        $late = 0;
        foreach ($todayClockIns as $records) {
            if ($records->first()->clock_in->format('H:i') <= self::LATE_AFTER) {
                $onTime++;
            } else {
                $late++;
            }
        }
        $attnAbsent = max(0, $totalEmployees - $onTime - $late);

        $departments = Employee::selectRaw('department, COUNT(*) as total')
            ->groupBy('department')
            ->orderByDesc('total')
            ->get();

        $designations = Employee::selectRaw('designation, COUNT(*) as total')
            ->groupBy('designation')
            ->orderByDesc('total')
            ->get();

        return [
            'totalEmployees' => $totalEmployees,
            'presentToday' => $presentToday,
            'absentToday' => max(0, $totalEmployees - $presentToday - $onLeaveToday),
            'onLeaveToday' => $onLeaveToday,
            // Only requests whose dates haven't already passed — stale ones
            // stay in the approval queue but no longer pad the dashboard.
            'pendingTotal' => Leave::where('status', Leave::STATUS_PENDING)->upcoming()->count(),
            // Today's attendance donut breakdown.
            'attnOnTime' => $onTime,
            'attnLate' => $late,
            'attnAbsent' => $attnAbsent,
            'departments' => $departments,
            'maxDept' => (int) ($departments->max('total') ?: 1),
            'designations' => $designations,
            'employeeList' => Employee::with('user')->latest('id')->take(8)->get(),
            'pendingLeaves' => Leave::with('employee.user')
                ->where('status', Leave::STATUS_PENDING)->upcoming()->oldest()->take(5)->get(),
            'announcements' => Announcement::visible()->latest()->take(5)->get(),
            'upcomingHolidays' => Holiday::whereDate('date', '>=', today())
                ->orderBy('date')->take(5)->get(),
            // Upcoming interview/meeting schedules (today onward).
            'schedules' => Schedule::whereDate('meeting_date', '>=', today())
                ->orderBy('meeting_date')->orderBy('start_time')->take(5)->get(),
            // Upcoming birthdays derived from each employee's date_of_birth.
            'birthdays' => $this->upcomingBirthdays(),
            // HR Action Center counters + the auto-written HR Briefing lines.
            'hrActions' => $this->hrActions(),
            'hrBriefing' => $this->hrBriefing(),
            'systemHealth' => $this->systemHealth(),
            'recentActivity' => Activity::with('user')->latestFirst()->take(6)->get(),
        ];
    }

    /**
     * Employees with an upcoming birthday, soonest first.
     * Each entry: ['employee' => Employee, 'date' => Carbon, 'daysUntil' => int].
     * Only birthdays within the next ~60 days are returned.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function upcomingBirthdays(): Collection
    {
        $today = today();

        return Employee::with('user')
            ->whereNotNull('date_of_birth')
            ->get()
            ->map(function (Employee $emp) use ($today) {
                $dob = $emp->date_of_birth;

                // Next occurrence of the birthday (this year, or next year if it already passed).
                $next = Carbon::create($today->year, (int) $dob->format('n'), (int) $dob->format('j'));
                if ($next->lt($today)) {
                    $next = $next->addYear();
                }

                return [
                    'employee' => $emp,
                    'date' => $next,
                    'daysUntil' => (int) $today->diffInDays($next),
                ];
            })
            ->filter(fn (array $b) => $b['daysUntil'] <= 60)
            ->sortBy('daysUntil')
            ->take(6)
            ->values();
    }

    /**
     * Employees whose probation review falls inside the review window, soonest
     * first. Derived from hire_date + PROBATION_MONTHS.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function probationReviews(): Collection
    {
        return Employee::with('user')
            ->where('status', Employee::STATUS_ACTIVE)
            ->whereNotNull('hire_date')
            ->get()
            ->map(function (Employee $employee) {
                $due = $employee->hire_date->copy()->addMonths(self::PROBATION_MONTHS);

                return [
                    'employee' => $employee,
                    'due' => $due,
                    // Negative = the review date has already passed.
                    'daysUntil' => (int) today()->diffInDays($due, false),
                ];
            })
            ->filter(fn (array $r) => abs($r['daysUntil']) <= self::PROBATION_WINDOW_DAYS)
            ->sortBy('daysUntil')
            ->values();
    }

    /**
     * Counters for the HR Action Center. Each row is something HR can act on
     * now, and links to the screen where they act on it.
     *
     * @return list<array<string, mixed>>
     */
    protected function hrActions(): array
    {
        $incompleteProfiles = Employee::where('status', Employee::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('date_of_birth')
                ->orWhereNull('phone')
                ->orWhereNull('department'))
            ->count();

        return [
            [
                'label' => 'Leave Approvals Pending',
                'count' => Leave::where('status', Leave::STATUS_PENDING)->upcoming()->count(),
                'url' => route('leaves.manage'),
                'tone' => 'amber',
            ],
            [
                'label' => 'Probation Reviews Due',
                'count' => $this->probationReviews()->count(),
                'url' => route('employees.index'),
                'tone' => 'blue',
            ],
            [
                'label' => 'Incomplete Profiles',
                'count' => $incompleteProfiles,
                'url' => route('employees.index'),
                'tone' => 'slate',
            ],
        ];
    }

    /**
     * Short auto-written lines for the HR Briefing panel: an attendance trend
     * followed by anything needing attention, or an all-clear.
     *
     * @return list<string>
     */
    protected function hrBriefing(): array
    {
        $thisWeek = Attendance::whereBetween('date', [
            now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString(),
        ])->count();

        $lastWeek = Attendance::whereBetween('date', [
            now()->subWeek()->startOfWeek()->toDateString(), now()->subWeek()->endOfWeek()->toDateString(),
        ])->count();

        if ($thisWeek === 0 && $lastWeek === 0) {
            $lines = ['No attendance recorded this week or last.'];
        } elseif ($lastWeek === 0) {
            $lines = ['Attendance logging resumed this week (' . $thisWeek . ' ' . $this->plural($thisWeek, 'record') . ').'];
        } else {
            $change = (int) round((($thisWeek - $lastWeek) / $lastWeek) * 100);
            $lines = [$change === 0
                ? 'Attendance is level with last week.'
                : 'Attendance ' . ($change > 0 ? 'rose' : 'dropped') . ' ' . abs($change) . '% vs last week.'];
        }

        $issues = [];

        $pending = Leave::where('status', Leave::STATUS_PENDING)->upcoming()->count();
        if ($pending > 0) {
            $issues[] = $pending . ' leave ' . $this->plural($pending, 'request') . ' waiting on approval.';
        }

        $dueThisWeek = $this->probationReviews()
            ->filter(fn (array $r) => $r['daysUntil'] >= 0 && $r['daysUntil'] <= 7)
            ->count();
        if ($dueThisWeek > 0) {
            $issues[] = $dueThisWeek . ' probation ' . $this->plural($dueThisWeek, 'review') . ' due this week.';
        }

        $overdue = $this->probationReviews()->filter(fn (array $r) => $r['daysUntil'] < 0)->count();
        if ($overdue > 0) {
            $issues[] = $overdue . ' probation ' . $this->plural($overdue, 'review') . ' now overdue.';
        }

        $birthdays = $this->upcomingBirthdays()->filter(fn (array $b) => $b['daysUntil'] <= 7)->count();
        if ($birthdays > 0) {
            $issues[] = $birthdays . ' ' . $this->plural($birthdays, 'birthday') . ' in the next 7 days.';
        }

        return $issues === []
            ? array_merge($lines, ['No critical issues to report.'])
            : array_merge($lines, $issues);
    }

    /**
     * Naive pluraliser for the briefing sentences.
     */
    private function plural(int $count, string $word): string
    {
        return $count === 1 ? $word : $word . 's';
    }

    /**
     * Infrastructure checks for the System Health panel.
     *
     * Every check is local: nothing here makes an outbound HTTP call, so
     * rendering the dashboard can never hang on a slow third party. The
     * TimeCamp row therefore reports whether it is *configured*, not whether
     * the remote API answered.
     *
     * @return list<array{label: string, status: string, detail: string}>
     */
    protected function systemHealth(): array
    {
        try {
            DB::connection()->getPdo();
            $database = ['ok', 'Connected'];
        } catch (\Throwable $e) {
            $database = ['fail', 'Unreachable'];
        }

        // The log/array mailers swallow mail instead of sending it, so nobody
        // actually receives password resets or notifications.
        $mailer = (string) config('mail.default');
        $mail = in_array($mailer, ['log', 'array'], true)
            ? ['warn', 'Not sending (' . $mailer . ' driver)']
            : ['ok', 'Configured (' . $mailer . ')'];

        // Profile photos need both the public symlink and a writable disk.
        if (! file_exists(public_path('storage'))) {
            $storage = ['fail', 'storage:link missing'];
        } elseif (! is_writable(storage_path('app'))) {
            $storage = ['fail', 'Disk not writable'];
        } else {
            $storage = ['ok', 'Linked and writable'];
        }

        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $jobs = $failed === 0
            ? ['ok', 'No failures']
            : ['fail', $failed . ' failed ' . $this->plural($failed, 'job')];

        $timecamp = config('services.timecamp.token')
            ? ['ok', 'Token configured']
            : ['warn', 'No API token set'];

        $rows = [
            ['Database', $database],
            ['Email delivery', $mail],
            ['File storage', $storage],
            ['Background jobs', $jobs],
            ['TimeCamp sync', $timecamp],
        ];

        return array_map(
            fn (array $row) => ['label' => $row[0], 'status' => $row[1][0], 'detail' => $row[1][1]],
            $rows
        );
    }
}
