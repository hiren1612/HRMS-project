<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsCalendarPanels;
use App\Http\Controllers\Concerns\BuildsManagerDashboard;
use App\Http\Controllers\Concerns\BuildsOrgDashboard;
use App\Models\Activity;
use App\Models\Announcement;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
    use BuildsCalendarPanels;
    use BuildsManagerDashboard;
    use BuildsOrgDashboard;

    /** Annual paid-leave allowance, in days, used for the leave balance. */
    private const ANNUAL_LEAVE_ALLOWANCE = 20;

    /**
     * The main dashboard. Admins get an organisation overview; everyone else
     * (with an employee profile) gets the self-service summary.
     */
    public function index(Request $request): View
    {
        // Admins and HR land on the organisation overview dashboard.
        if ($request->user()->hasAnyRole(['admin', 'hr'])) {
            return view('dashboard', ['adminDash' => $this->orgDashboard()]);
        }

        // Managers get a team-scoped overview of their own reports.
        if ($request->user()->hasRole('manager')) {
            return view('dashboard', ['mgrDash' => $this->managerDashboard($request->user())]);
        }

        $employee = $request->user()->employee;
        $empDash = $employee ? $this->employeeDashboard($employee) : null;

        return view('dashboard', compact('empDash'));
    }

    /**
     * Build the employee self-service dashboard: today's status, productive
     * hours, leave balance, attendance summary, recent leaves, holidays and
     * announcements.
     *
     * @return array<string, mixed>
     */
    private function employeeDashboard(Employee $employee): array
    {
        $isPresent = $employee->attendances()->whereDate('date', today())->exists();

        // Productive minutes today / this week / this month.
        $todayProductive = $this->productiveSum($employee, today(), today());
        $weekProductive = $this->productiveSum($employee, now()->startOfWeek(), now()->endOfWeek());
        $monthProductive = $this->productiveSum($employee, now()->startOfMonth(), now()->endOfMonth());

        // Distinct days present this month.
        $presentDaysMonth = $employee->attendances()
            ->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->distinct('date')->count('date');

        // Total remaining leave across all categories (paid/unpaid/special/carry).
        $balances = $employee->leaveBalances();
        $leaveRemaining = round(collect($balances)->sum('remaining'), 1);

        return [
            'employee' => $employee,
            'isPresent' => $isPresent,
            'todayProductive' => $todayProductive,
            'weekProductive' => $weekProductive,
            'monthProductive' => $monthProductive,
            'presentDaysMonth' => $presentDaysMonth,
            'leaveRemaining' => $leaveRemaining,
            'pendingLeaves' => $employee->leaves()->where('status', Leave::STATUS_PENDING)->count(),
            'recentLeaves' => $employee->leaves()->latest()->take(5)->get(),
            'upcomingHolidays' => Holiday::whereDate('date', '>=', today())->orderBy('date')->take(5)->get(),
            'announcements' => Announcement::latest()->take(5)->get(),
            // Upcoming meetings + birthdays (shared widgets, also on the org dashboard).
            'schedules' => Schedule::whereDate('meeting_date', '>=', today())
                ->orderBy('meeting_date')->orderBy('start_time')->take(5)->get(),
            'birthdays' => $this->upcomingBirthdays(),
            // Meetings + holidays merged for the "Schedule & Holidays" card,
            // and a date=>events map used to mark days in the calendar.
            'agenda' => $this->upcomingAgenda(),
            'calendar' => $this->calendarEvents(),
            // This employee's own milestones (birthday, anniversary, their leave).
            'upcoming' => $this->personalUpcoming($employee),
            // Goals HR has assigned to this employee, least complete first.
            'recentActivity' => Activity::where('user_id', $employee->user_id)
                ->latestFirst()->take(6)->get(),
            'myGoals' => $employee->goals()->active()
                ->orderBy('progress')->orderByRaw('due_date IS NULL, due_date')->take(5)->get(),
        ];
    }

    /**
     * Sum productive minutes (worked - break, floored at 0) between two dates.
     */
    private function productiveSum(Employee $employee, \Carbon\Carbon $from, \Carbon\Carbon $to): int
    {
        return $employee->productiveMinutesBetween($from, $to);
    }

    /**
     * Personal milestones for the dashboard "Upcoming" card.
     *
     * Deliberately limited to dates about *this employee* — their birthday,
     * work anniversary, probation confirmation and their own booked leave.
     * Company-wide holidays and meetings are not repeated here; those already
     * have their own "Schedule & Holidays" card.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function personalUpcoming(Employee $employee): Collection
    {
        $items = collect();

        if ($employee->date_of_birth) {
            $items->push([
                'label' => 'Birthday',
                'date' => $this->nextAnniversaryOf($employee->date_of_birth),
                'type' => 'birthday',
                'meta' => null,
            ]);
        }

        if ($employee->hire_date) {
            $anniversary = $this->nextAnniversaryOf($employee->hire_date);
            $years = (int) $employee->hire_date->diffInYears($anniversary);

            $items->push([
                'label' => 'Work Anniversary',
                'date' => $anniversary,
                'type' => 'anniversary',
                'meta' => $years . ' ' . ($years === 1 ? 'year' : 'years'),
            ]);

            // Probation confirmation, only while it is still ahead of them.
            $probation = $employee->hire_date->copy()->addMonths(self::PROBATION_MONTHS);
            if ($probation->gte(today())) {
                $items->push([
                    'label' => 'Probation Confirmation',
                    'date' => $probation,
                    'type' => 'probation',
                    'meta' => null,
                ]);
            }
        }

        $booked = $employee->leaves()
            ->where('status', Leave::STATUS_APPROVED)
            ->whereDate('start_date', '>=', today())
            ->orderBy('start_date')->take(2)->get();

        foreach ($booked as $leave) {
            $days = $leave->dayCount();
            $items->push([
                'label' => $leave->typeLabel(),
                'date' => $leave->start_date,
                'type' => 'leave',
                'meta' => rtrim(rtrim(number_format($days, 1), '0'), '.') . ' day' . ($days === 1.0 ? '' : 's'),
            ]);
        }

        return $items->sortBy('date')->take(5)->values();
    }

    /**
     * The next time a day-and-month recurs — this year if still ahead,
     * otherwise next year.
     */
    private function nextAnniversaryOf(\Carbon\Carbon $date): \Carbon\Carbon
    {
        $next = \Carbon\Carbon::create(today()->year, (int) $date->format('n'), (int) $date->format('j'));

        return $next->lt(today()) ? $next->addYear() : $next;
    }
}
