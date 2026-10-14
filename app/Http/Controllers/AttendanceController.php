<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /**
     * Admins see a read-only list of ALL employees' attendance.
     * Everyone else gets the self-service clock in/out + productive hours view.
     */
    public function index(Request $request): View
    {
        // HR needs the organisation-wide view too, not just their own (QA H7).
        if ($request->user()->hasAnyRole([\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_HR])) {
            $attendances = Attendance::with('employee.user')
                ->latest('clock_in')
                ->paginate(20);

            return view('attendance.admin', compact('attendances'));
        }

        $employee = $request->user()->employee;

        // The currently-open session (clocked in, not yet out), if any.
        $openSession = $employee?->attendances()
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();

        $attendances = $employee
            ? $employee->attendances()->latest('clock_in')->paginate(15)
            : new LengthAwarePaginator([], 0, 15);

        // Productive-hours totals for the three periods (completed sessions only).
        $todayMinutes = $this->productiveMinutes($employee, today(), today());
        $weekMinutes = $this->productiveMinutes($employee, now()->startOfWeek(), now()->endOfWeek());
        $monthMinutes = $this->productiveMinutes($employee, now()->startOfMonth(), now()->endOfMonth());

        return view('attendance.index', compact(
            'employee', 'openSession', 'attendances', 'todayMinutes', 'weekMinutes', 'monthMinutes'
        ));
    }

    /**
     * Sum productive minutes between two dates (inclusive) for an employee.
     *
     * productive = (clock_out - clock_in) - break_minutes, floored at 0.
     * Done in SQL so we don't load every row into PHP.
     */
    private function productiveMinutes(?\App\Models\Employee $employee, \Carbon\Carbon $from, \Carbon\Carbon $to): int
    {
        if (! $employee) {
            return 0;
        }

        return $employee->productiveMinutesBetween($from, $to);
    }

    /**
     * Record/update the break time (in minutes) for today's session.
     */
    public function break(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'break_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
        ]);

        $employee = $request->user()->employee;

        // Apply to the most recent session for today (open or just closed).
        $session = $employee?->attendances()
            ->whereDate('date', today())
            ->latest('clock_in')
            ->first();

        if (! $session) {
            return back()->with('error', 'Clock in first before recording break time.');
        }

        // A break cannot be longer than the time actually worked (QA H2).
        // For a session still open, measure against the time elapsed so far.
        $end = $session->clock_out ?? now();
        $worked = (int) $session->clock_in->diffInMinutes($end);

        if ($validated['break_minutes'] > $worked) {
            return back()->with('error',
                "Break of {$validated['break_minutes']} minutes is longer than the {$worked} minutes worked so far.");
        }

        $session->update(['break_minutes' => $validated['break_minutes']]);

        return back()->with('success', "Break time set to {$validated['break_minutes']} minutes.");
    }

    /**
     * Clock in — opens a new session for today.
     */
    public function clockIn(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return back()->with('error', 'You need an employee profile to clock in. Please contact HR.');
        }

        // Guard: don't allow a second open session.
        $alreadyOpen = $employee->attendances()->whereNull('clock_out')->exists();
        if ($alreadyOpen) {
            return back()->with('error', 'You are already clocked in.');
        }

        $employee->attendances()->create([
            'date' => today(),
            'clock_in' => now(),
        ]);

        return back()->with('success', 'Clocked in at ' . now()->format('h:i A') . '.');
    }

    /**
     * Clock out — closes the open session and reports hours worked.
     */
    public function clockOut(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;

        $openSession = $employee?->attendances()
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();

        if (! $openSession) {
            return back()->with('error', 'You are not clocked in.');
        }

        $openSession->update(['clock_out' => now()]);

        return back()->with('success', 'Clocked out at ' . now()->format('h:i A') . '. Worked ' . $openSession->workedLabel() . '.');
    }
}
