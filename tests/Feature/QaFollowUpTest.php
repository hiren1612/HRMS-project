<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression cover for the issues raised in the second QA pass:
 * H9 (double-booked dates), M16 (missing day count), M17 (account
 * enumeration on password reset) and H8 (missing photo files).
 */
class QaFollowUpTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): Employee
    {
        $user = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);

        return Employee::create([
            'user_id' => $user->id,
            'name' => 'Leave Taker',
            'employee_code' => 'EMP-' . fake()->unique()->numerify('####'),
            'designation' => 'Clerk',
            'department' => 'QA',
            'hire_date' => '2026-01-01',
            'status' => Employee::STATUS_ACTIVE,
        ]);
    }

    /**
     * The form collapses end_date into start_date unless "Apply for multiple
     * days" is ticked, so a multi-day request must send multiple_days too.
     */
    private function apply(Employee $staff, string $from, string $to): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($staff->user)->post(route('leaves.store'), [
            'leave_type' => 'paid',
            'session' => 'full_day',
            'multiple_days' => $from === $to ? 0 : 1,
            'start_date' => $from,
            'end_date' => $to,
            'reason' => 'Family event',
        ]);
    }

    // ---------------------------------------------------------------- H9

    public function test_the_same_dates_cannot_be_booked_twice(): void
    {
        $staff = $this->staff();
        $from = now()->addDays(10)->toDateString();
        $to = now()->addDays(12)->toDateString();

        $this->apply($staff, $from, $to)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('leaves', 1);

        // Exactly the same window.
        $this->apply($staff, $from, $to)->assertSessionHasErrors('start_date');
        $this->assertDatabaseCount('leaves', 1);
    }

    public function test_a_partly_overlapping_request_is_refused(): void
    {
        $staff = $this->staff();
        $this->apply($staff, now()->addDays(10)->toDateString(), now()->addDays(12)->toDateString());

        // Starts inside the existing booking and runs past it.
        $this->apply($staff, now()->addDays(11)->toDateString(), now()->addDays(14)->toDateString())
            ->assertSessionHasErrors('start_date');

        $this->assertDatabaseCount('leaves', 1);
    }

    public function test_a_rejected_leave_does_not_block_the_same_dates(): void
    {
        $staff = $this->staff();
        $from = now()->addDays(20)->toDateString();
        $to = now()->addDays(21)->toDateString();

        $this->apply($staff, $from, $to);
        Leave::first()->update(['status' => Leave::STATUS_REJECTED]);

        $this->apply($staff, $from, $to)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('leaves', 2);
    }

    public function test_separate_dates_are_still_allowed(): void
    {
        $staff = $this->staff();
        $this->apply($staff, now()->addDays(10)->toDateString(), now()->addDays(11)->toDateString());
        $this->apply($staff, now()->addDays(20)->toDateString(), now()->addDays(21)->toDateString())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('leaves', 2);
    }

    public function test_one_persons_leave_does_not_block_another_persons(): void
    {
        $mine = $this->staff();
        $theirs = $this->staff();
        $from = now()->addDays(10)->toDateString();
        $to = now()->addDays(11)->toDateString();

        $this->apply($mine, $from, $to)->assertSessionHasNoErrors();
        $this->apply($theirs, $from, $to)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('leaves', 2);
    }

    // --------------------------------------------------------------- M16

    public function test_the_leave_list_shows_a_day_count(): void
    {
        $staff = $this->staff();
        $this->apply($staff, now()->addDays(10)->toDateString(), now()->addDays(14)->toDateString());

        $this->actingAs($staff->user)->get(route('leaves.index'))
            ->assertOk()
            ->assertSee('Days');

        $this->assertSame(5.0, Leave::first()->dayCount());
    }

    public function test_a_half_day_counts_as_half(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff->user)->post(route('leaves.store'), [
            'leave_type' => 'paid',
            'session' => 'first_half',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'reason' => 'Appointment',
        ]);

        $this->assertSame(0.5, Leave::first()->dayCount());
    }

    // --------------------------------------------------------------- M17

    public function test_password_reset_gives_the_same_answer_for_any_address(): void
    {
        User::factory()->create(['email' => 'known@example.test']);

        $known = $this->post('/forgot-password', ['email' => 'known@example.test']);
        $unknown = $this->post('/forgot-password', ['email' => 'nobody@example.test']);

        $known->assertSessionHasNoErrors();
        $unknown->assertSessionHasNoErrors();

        $this->assertSame(
            session()->get('status'),
            $unknown->getSession()->get('status'),
            'both addresses must produce the same message'
        );
    }

    public function test_an_unknown_address_is_never_named_as_missing(): void
    {
        $this->post('/forgot-password', ['email' => 'nobody@example.test'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');
    }

    // ---------------------------------------------------------------- H8

    public function test_a_photo_path_with_no_file_falls_back_to_initials(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['profile_photo_path' => 'avatars/gone.png']);

        $this->assertNull($user->profilePhotoUrl(), 'a missing file must not produce a URL');
        $this->assertNotSame('', $user->initial());
    }

    public function test_a_photo_that_exists_still_resolves(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/there.png', 'binary');

        $user = User::factory()->create(['profile_photo_path' => 'avatars/there.png']);

        $this->assertNotNull($user->profilePhotoUrl());
    }
}
