<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Activity;
use App\Models\Announcement;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\Leave;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Data for the Manager Dashboard.
 *
 * "My team" is every employee whose employees.manager_id points at this
 * manager's user account, so a manager sees only their own reports —
 * never the whole organisation.
 */
trait BuildsManagerDashboard
{
    use BuildsCalendarPanels;

    /**
     * @return array<string, mixed>
     */
    protected function managerDashboard(User $manager): array
    {
        $team = Employee::with('user')
            ->where('manager_id', $manager->id)
            ->where('status', Employee::STATUS_ACTIVE)
            ->get();

        $ids = $team->pluck('id')->all();

        // --- today's attendance across the team -------------------------
        $presentToday = $ids === [] ? 0 : Employee::whereIn('id', $ids)
            ->whereHas('attendances', fn ($q) => $q->whereDate('date', today()))
            ->count();

        $onLeaveToday = $ids === [] ? 0 : Leave::whereIn('employee_id', $ids)
            ->where('status', Leave::STATUS_APPROVED)
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->distinct('employee_id')->count('employee_id');

        // --- goals across the team --------------------------------------
        $goals = $ids === []
            ? collect()
            : Goal::whereIn('employee_id', $ids)->active()->get();

        $atRisk = $goals->filter(fn (Goal $g) => $g->isOverdue());

        $pendingLeaves = $ids === []
            ? collect()
            : Leave::with('employee.user')
                ->whereIn('employee_id', $ids)
                ->where('status', Leave::STATUS_PENDING)
                ->upcoming()
                ->orderBy('start_date')->get();

        return [
            'manager' => $manager,
            'teamSize' => $team->count(),
            'presentToday' => $presentToday,
            'onLeaveToday' => $onLeaveToday,
            'absentToday' => max(0, $team->count() - $presentToday - $onLeaveToday),
            'pendingLeaves' => $pendingLeaves,
            'goalCompletion' => $goals->isEmpty() ? null : (int) round($goals->avg('progress')),
            'goalsOnTrack' => $goals->count() - $atRisk->count(),
            'goalsAtRisk' => $atRisk->count(),
            'team' => $this->teamRows($team),
            'announcements' => Announcement::latest()->take(4)->get(),
            // Same calendar panels the employee dashboard uses, with birthdays
            // narrowed to this manager's own reports.
            'calendar' => $this->calendarEvents(),
            'agenda' => $this->upcomingAgenda(),
            'birthdays' => $this->teamBirthdays($team),
            // The manager's own actions plus those of their reports.
            'recentActivity' => Activity::with('user')
                ->whereIn('user_id', $team->pluck('user_id')->push($manager->id)->filter()->all())
                ->latestFirst()->take(6)->get(),
        ];
    }

    /**
     * One row per team member: attendance rate this month and average goal
     * progress, strongest performer first.
     *
     * @param  Collection<int, Employee>  $team
     * @return Collection<int, array<string, mixed>>
     */
    private function teamRows(Collection $team): Collection
    {
        $workdays = $this->workdaysSoFarThisMonth();

        return $team->map(function (Employee $employee) use ($workdays) {
            $present = $employee->attendances()
                ->whereBetween('date', [
                    now()->startOfMonth()->toDateString(),
                    today()->toDateString(),
                ])
                ->distinct('date')->count('date');

            $goals = $employee->goals()->active()->get();

            return [
                'employee' => $employee,
                'attendance' => $workdays === 0 ? null : min(100, (int) round($present / $workdays * 100)),
                'performance' => $goals->isEmpty() ? null : (int) round($goals->avg('progress')),
                'goalCount' => $goals->count(),
            ];
        })->sortByDesc(fn (array $row) => $row['performance'] ?? -1)->values();
    }

    /**
     * Weekdays from the 1st of the month up to and including today. Used as
     * the denominator for an attendance percentage.
     */
    private function workdaysSoFarThisMonth(): int
    {
        $day = now()->startOfMonth();
        $count = 0;

        while ($day->lte(today())) {
            if (! $day->isWeekend()) {
                $count++;
            }
            $day = $day->addDay();
        }

        return $count;
    }

    /**
     * Upcoming birthdays for this manager's team, soonest first.
     *
     * Unlike the company-wide card this looks a full year ahead, so a small
     * team whose birthdays are months away still gets a usable list.
     *
     * @param  Collection<int, Employee>  $team
     * @return Collection<int, array<string, mixed>>
     */
    private function teamBirthdays(Collection $team): Collection
    {
        $today = today();

        return $team->filter(fn (Employee $e) => $e->date_of_birth !== null)
            ->map(function (Employee $employee) use ($today) {
                $dob = $employee->date_of_birth;

                $next = \Carbon\Carbon::create($today->year, (int) $dob->format('n'), (int) $dob->format('j'));
                if ($next->lt($today)) {
                    $next = $next->addYear();
                }

                return [
                    'employee' => $employee,
                    'date' => $next,
                    'daysUntil' => (int) $today->diffInDays($next),
                ];
            })
            ->sortBy('daysUntil')
            ->take(6)
            ->values();
    }
}
