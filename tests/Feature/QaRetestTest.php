<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Goal;
use App\Models\Leave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cover for the retest round: N1 (HR lost its clock-in button), M13 (the
 * 100%-completes hook never actually ran), M3, M4, M5 and M6.
 */
class QaRetestTest extends TestCase
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

    private function pendingLeave(Employee $staff, string $from = null): Leave
    {
        $from ??= now()->addDays(10)->toDateString();

        return Leave::create([
            'employee_id' => $staff->id,
            'leave_type' => 'paid',
            'session' => 'full_day',
            'start_date' => $from,
            'end_date' => $from,
            'reason' => 'Family event',
            'status' => Leave::STATUS_PENDING,
        ]);
    }

    // ---------------------------------------------------------------- M13

    public function test_a_goal_saved_at_100_percent_is_completed(): void
    {
        $staff = $this->person();

        $goal = Goal::create([
            'employee_id' => $staff->id, 'title' => 'Done', 'progress' => 100,
            'status' => Goal::STATUS_ACTIVE,
        ]);

        $this->assertSame(Goal::STATUS_COMPLETED, $goal->fresh()->status);
    }

    public function test_dropping_below_100_percent_reopens_a_goal(): void
    {
        $staff = $this->person();
        $goal = Goal::create([
            'employee_id' => $staff->id, 'title' => 'Done', 'progress' => 100,
            'status' => Goal::STATUS_ACTIVE,
        ]);

        $goal->update(['progress' => 40]);

        $this->assertSame(Goal::STATUS_ACTIVE, $goal->fresh()->status);
    }

    public function test_a_cancelled_goal_is_not_dragged_back_to_active(): void
    {
        $staff = $this->person();
        $goal = Goal::create([
            'employee_id' => $staff->id, 'title' => 'Dropped', 'progress' => 20,
            'status' => Goal::STATUS_CANCELLED,
        ]);

        $goal->update(['progress' => 25]);

        $this->assertSame(Goal::STATUS_CANCELLED, $goal->fresh()->status);
    }

    // ----------------------------------------------------------------- N1

    public function test_hr_sees_both_the_org_list_and_their_own_clock_in(): void
    {
        $hr = $this->person(User::ROLE_HR);
        $other = $this->person();
        $this->pendingLeave($other);

        $this->actingAs($hr->user)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Start Time')          // their own panel is back
            ->assertSee('All Attendance', false); // and the org view remains
    }

    public function test_an_admin_also_keeps_a_clock_in_button(): void
    {
        $admin = $this->person(User::ROLE_ADMIN);

        $this->actingAs($admin->user)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Start Time');
    }

    // ----------------------------------------------------------------- M4

    public function test_an_employee_can_cancel_their_own_pending_leave(): void
    {
        $staff = $this->person();
        $leave = $this->pendingLeave($staff);

        $this->actingAs($staff->user)->patch(route('leaves.cancel', $leave))
            ->assertRedirect();

        $this->assertSame(Leave::STATUS_CANCELLED, $leave->fresh()->status);
    }

    public function test_an_approved_leave_cannot_be_cancelled(): void
    {
        $staff = $this->person();
        $leave = $this->pendingLeave($staff);
        $leave->update(['status' => Leave::STATUS_APPROVED]);

        $this->actingAs($staff->user)->patch(route('leaves.cancel', $leave))->assertForbidden();
        $this->assertSame(Leave::STATUS_APPROVED, $leave->fresh()->status);
    }

    public function test_one_employee_cannot_cancel_anothers_leave(): void
    {
        $mine = $this->person();
        $theirs = $this->person();
        $leave = $this->pendingLeave($theirs);

        $this->actingAs($mine->user)->patch(route('leaves.cancel', $leave))->assertForbidden();
        $this->assertSame(Leave::STATUS_PENDING, $leave->fresh()->status);
    }

    public function test_a_cancelled_leave_frees_the_dates_again(): void
    {
        $staff = $this->person();
        $from = now()->addDays(10)->toDateString();
        $leave = $this->pendingLeave($staff, $from);

        $this->actingAs($staff->user)->patch(route('leaves.cancel', $leave));

        $this->actingAs($staff->user)->post(route('leaves.store'), [
            'leave_type' => 'paid', 'session' => 'full_day',
            'start_date' => $from, 'end_date' => $from, 'reason' => 'Rebooked',
        ])->assertSessionHasNoErrors();
    }

    // ----------------------------------------------------------------- M3

    public function test_rejecting_a_leave_requires_a_reason(): void
    {
        $hr = $this->person(User::ROLE_HR);
        $leave = $this->pendingLeave($this->person());

        $this->actingAs($hr->user)->patch(route('leaves.reject', $leave), [])
            ->assertSessionHasErrors('decision_note');

        $this->assertSame(Leave::STATUS_PENDING, $leave->fresh()->status);
    }

    public function test_the_rejection_reason_is_stored_and_shown(): void
    {
        $hr = $this->person(User::ROLE_HR);
        $staff = $this->person();
        $leave = $this->pendingLeave($staff);

        $this->actingAs($hr->user)->patch(route('leaves.reject', $leave), [
            'decision_note' => 'Two people already off that week',
        ])->assertRedirect();

        $leave->refresh();
        $this->assertSame(Leave::STATUS_REJECTED, $leave->status);
        $this->assertSame('Two people already off that week', $leave->decision_note);

        $this->actingAs($staff->user)->get(route('leaves.show', $leave))
            ->assertOk()
            ->assertSee('Two people already off that week');
    }

    // ----------------------------------------------------------------- M5

    public function test_leave_can_be_back_dated_within_a_month(): void
    {
        $staff = $this->person();

        $this->actingAs($staff->user)->post(route('leaves.store'), [
            'leave_type' => 'paid', 'session' => 'full_day',
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->subDays(3)->toDateString(),
            'reason' => 'Was unwell',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('leaves', 1);
    }

    public function test_leave_cannot_be_back_dated_beyond_a_month(): void
    {
        $staff = $this->person();

        $this->actingAs($staff->user)->post(route('leaves.store'), [
            'leave_type' => 'paid', 'session' => 'full_day',
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->subMonths(3)->toDateString(),
            'reason' => 'Far too late',
        ])->assertSessionHasErrors('start_date');

        $this->assertDatabaseCount('leaves', 0);
    }

    // ----------------------------------------------------------------- M6

    public function test_a_finished_day_does_not_say_you_have_not_started(): void
    {
        $staff = $this->person();
        $staff->attendances()->create([
            'date' => today()->toDateString(),
            'clock_in' => today()->toDateString() . ' 09:00:00',
            'clock_out' => today()->toDateString() . ' 17:00:00',
            'break_minutes' => 30,
        ]);

        $this->actingAs($staff->user)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Day completed')
            ->assertDontSee('You have not started your day yet');
    }
}
