<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Retest 2 items: H1/N2 (forgotten clock-outs and the 2,883-hour session),
 * M16 (working-day leave counting), M5 (back-dated picker) and M7
 * (attendance filters, export and HR correction).
 */
class QaRetest2Test extends TestCase
{
    use RefreshDatabase;

    private function person(string $role = User::ROLE_EMPLOYEE): Employee
    {
        return Employee::create([
            'user_id' => User::factory()->create(['role' => $role])->id,
            'name' => 'Test Person',
            'employee_code' => 'EMP-' . fake()->unique()->numerify('####'),
            'designation' => 'Clerk',
            'department' => 'QA',
            'hire_date' => '2026-01-01',
            'status' => Employee::STATUS_ACTIVE,
        ]);
    }

    private function logSession(Employee $staff, string $in, ?string $out, int $break = 0): Attendance
    {
        return Attendance::create([
            'employee_id' => $staff->id,
            'date' => Carbon::parse($in)->toDateString(),
            'clock_in' => $in,
            'clock_out' => $out,
            'break_minutes' => $break,
        ]);
    }

    // ------------------------------------------------------- H1 / N2

    public function test_an_implausible_session_cannot_inflate_productive_time(): void
    {
        $staff = $this->person();
        // The real data had a 2,879-hour row. It must not bank 2,879 hours.
        $session = $this->logSession($staff, '2026-06-17 11:32:00', '2026-10-15 10:00:00');

        $this->assertTrue($session->isSuspicious());
        $this->assertSame(Attendance::MAX_SESSION_MINUTES, $session->productive_minutes);
    }

    public function test_a_normal_day_is_untouched_by_the_cap(): void
    {
        $staff = $this->person();
        $session = $this->logSession($staff, '2026-10-12 09:00:00', '2026-10-12 17:00:00', 30);

        $this->assertFalse($session->isSuspicious());
        $this->assertSame(450, $session->productive_minutes); // 480 - 30
    }

    public function test_a_session_ending_on_a_later_day_is_detectable(): void
    {
        $staff = $this->person();

        $this->assertTrue($this->logSession($staff, '2026-10-12 22:00:00', '2026-10-13 02:00:00')->spansDays());
        $this->assertFalse($this->logSession($staff, '2026-10-14 09:00:00', '2026-10-14 17:00:00')->spansDays());
    }

    public function test_the_sweep_closes_a_forgotten_session_at_a_plausible_time(): void
    {
        $staff = $this->person();
        $open = $this->logSession($staff, now()->subDays(40)->setTime(9, 0)->toDateTimeString(), null);

        $this->artisan('attendance:close-stale')->assertSuccessful();

        $open->refresh();
        $this->assertNotNull($open->clock_out, 'the session should be closed');
        $this->assertSame(Attendance::CLOSED_AUTO, $open->closed_by);
        // Closed 12 hours after it opened, not 40 days later.
        $this->assertSame(
            Attendance::MAX_SESSION_MINUTES,
            (int) $open->clock_in->diffInMinutes($open->clock_out)
        );
    }

    public function test_the_sweep_leaves_a_session_from_today_alone(): void
    {
        $staff = $this->person();
        $open = $this->logSession($staff, now()->subHour()->toDateTimeString(), null);

        $this->artisan('attendance:close-stale')->assertSuccessful();

        $this->assertNull($open->fresh()->clock_out);
    }

    // ------------------------------------------------------------ M16

    public function test_leave_skips_weekends(): void
    {
        // 20-23 Nov 2026 is Fri to Mon: two working days, not four.
        $this->assertSame(2.0, Leave::workingDaysBetween(
            Carbon::parse('2026-11-20'), Carbon::parse('2026-11-23')
        ));
    }

    public function test_leave_skips_public_holidays(): void
    {
        Holiday::create(['title' => 'Test Holiday', 'date' => '2026-11-24']);

        // Mon 23rd and Wed 25th count; Tue 24th is a holiday.
        $this->assertSame(2.0, Leave::workingDaysBetween(
            Carbon::parse('2026-11-23'), Carbon::parse('2026-11-25')
        ));
    }

    public function test_a_weekend_only_request_costs_nothing(): void
    {
        $this->assertSame(0.0, Leave::workingDaysBetween(
            Carbon::parse('2026-11-21'), Carbon::parse('2026-11-22')
        ));
    }

    public function test_a_half_day_is_still_half_a_day(): void
    {
        $staff = $this->person();
        $leave = Leave::create([
            'employee_id' => $staff->id, 'leave_type' => 'paid', 'session' => 'first_half',
            'start_date' => '2026-11-23', 'end_date' => '2026-11-23',
            'reason' => 'Appointment', 'status' => Leave::STATUS_PENDING,
        ]);

        $this->assertSame(0.5, $leave->dayCount());
    }

    // ------------------------------------------------------------- M5

    public function test_the_apply_form_allows_picking_a_past_date(): void
    {
        $staff = $this->person();

        $this->actingAs($staff->user)->get(route('leaves.create'))
            ->assertOk()
            ->assertSee('min="' . now()->subMonth()->toDateString() . '"', false);
    }

    // ------------------------------------------------------------- M7

    public function test_attendance_can_be_filtered_by_employee(): void
    {
        $hr = $this->person(User::ROLE_HR);
        $a = $this->person();
        $b = $this->person();
        $this->logSession($a, '2026-10-12 09:00:00', '2026-10-12 17:00:00');
        $this->logSession($b, '2026-10-12 09:00:00', '2026-10-12 17:00:00');

        $response = $this->actingAs($hr->user)->get(route('attendance.index', ['employee' => $a->id]));

        $this->assertSame(1, $response->viewData('attendances')->total());
    }

    public function test_attendance_exports_as_csv(): void
    {
        $hr = $this->person(User::ROLE_HR);
        $this->logSession($this->person(), '2026-10-12 09:00:00', '2026-10-12 17:00:00');

        $this->actingAs($hr->user)->get(route('attendance.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_an_employee_cannot_export_attendance(): void
    {
        $staff = $this->person();

        $this->actingAs($staff->user)->get(route('attendance.export'))->assertForbidden();
    }

    public function test_hr_can_correct_a_wrong_session_with_a_reason(): void
    {
        $hr = $this->person(User::ROLE_HR);
        $session = $this->logSession($this->person(), '2026-06-17 11:32:00', '2026-10-15 10:00:00');

        $this->actingAs($hr->user)->patch(route('attendance.correct', $session), [
            'clock_in' => '2026-06-17 11:32:00',
            'clock_out' => '2026-06-17 18:00:00',
            'break_minutes' => 30,
            'correction_note' => 'Forgot to clock out; confirmed with the employee',
        ])->assertRedirect();

        $session->refresh();
        $this->assertFalse($session->isSuspicious());
        $this->assertSame(Attendance::CLOSED_HR, $session->closed_by);
        $this->assertSame(358, $session->productive_minutes); // 388 - 30
    }

    public function test_a_correction_requires_a_reason(): void
    {
        $hr = $this->person(User::ROLE_HR);
        $session = $this->logSession($this->person(), '2026-10-12 09:00:00', '2026-10-12 17:00:00');

        $this->actingAs($hr->user)->patch(route('attendance.correct', $session), [
            'clock_in' => '2026-10-12 09:00:00',
            'clock_out' => '2026-10-12 17:00:00',
            'break_minutes' => 0,
        ])->assertSessionHasErrors('correction_note');
    }

    public function test_an_employee_cannot_correct_attendance(): void
    {
        $staff = $this->person();
        $session = $this->logSession($staff, '2026-10-12 09:00:00', '2026-10-12 17:00:00');

        $this->actingAs($staff->user)->patch(route('attendance.correct', $session), [
            'clock_in' => '2026-10-12 06:00:00',
            'clock_out' => '2026-10-12 23:00:00',
            'break_minutes' => 0,
            'correction_note' => 'More hours please',
        ])->assertForbidden();
    }
}
