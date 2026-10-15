<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Round 3 of the QA list: the items reported as "not yet attempted".
 *
 * M9 (department/designation dropdowns), L2/L1 (announcement management),
 * U1 (tables on a phone), U2 (required markers), U6 (one date format) and
 * U8 (themed confirmation instead of the browser's own box).
 */
class QaRetest3Test extends TestCase
{
    use RefreshDatabase;

    private function person(string $role = User::ROLE_EMPLOYEE): Employee
    {
        return Employee::create([
            'user_id' => User::factory()->create(['role' => $role])->id,
            'name' => 'Test Person',
            'employee_code' => 'EMP-' . fake()->unique()->numerify('####'),
            'designation' => 'Software Developer',
            'department' => 'Engineering',
            'hire_date' => '2026-01-01',
            'status' => Employee::STATUS_ACTIVE,
        ]);
    }

    /** @return array<string, mixed> */
    private function employeePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Starter',
            'employee_code' => 'EMP-' . fake()->unique()->numerify('####'),
            'designation' => 'Software Developer',
            'department' => 'Engineering',
            'hire_date' => '2026-02-01',
            'status' => Employee::STATUS_ACTIVE,
        ], $overrides);
    }

    // ------------------------------------------------------------- M9

    public function test_the_employee_form_offers_departments_as_a_dropdown(): void
    {
        $hr = $this->person(User::ROLE_HR);

        $this->actingAs($hr->user)->get(route('employees.create'))
            ->assertOk()
            ->assertSee('<select id="department" name="department"', false)
            ->assertSee('<select id="designation" name="designation"', false)
            ->assertSee('Information Technology')
            ->assertSee('HR Manager');
    }

    public function test_a_value_already_in_the_database_stays_in_the_list(): void
    {
        $hr = $this->person(User::ROLE_HR);
        // A department nobody configured, saved before the dropdown existed.
        $this->person()->update(['department' => 'Legacy Division']);

        $this->actingAs($hr->user)->get(route('employees.create'))
            ->assertOk()
            ->assertSee('Legacy Division');
    }

    public function test_choosing_other_saves_what_was_typed(): void
    {
        $hr = $this->person(User::ROLE_HR);

        $this->actingAs($hr->user)->post(route('employees.store'), $this->employeePayload([
            'name' => 'Rani',
            'department' => '__other',
            'department_other' => '  Research and Development  ',
        ]))->assertRedirect();

        // Trimmed, and the sentinel never reaches the database.
        $this->assertDatabaseHas('employees', [
            'name' => 'Rani',
            'department' => 'Research and Development',
        ]);
        $this->assertDatabaseMissing('employees', ['department' => '__other']);
    }

    public function test_choosing_other_without_typing_anything_is_rejected(): void
    {
        $hr = $this->person(User::ROLE_HR);

        $this->actingAs($hr->user)->post(route('employees.store'), $this->employeePayload([
            'department' => '__other',
            'department_other' => '',
        ]))->assertSessionHasErrors('department');
    }

    public function test_other_also_works_when_editing(): void
    {
        $hr = $this->person(User::ROLE_HR);
        $staff = $this->person();

        $this->actingAs($hr->user)->put(route('employees.update', $staff), $this->employeePayload([
            'name' => $staff->name,
            'employee_code' => $staff->employee_code,
            'designation' => '__other',
            'designation_other' => 'Chief of Staff',
        ]))->assertRedirect();

        $this->assertSame('Chief of Staff', $staff->fresh()->designation);
    }

    public function test_a_plain_dropdown_choice_still_saves_normally(): void
    {
        $hr = $this->person(User::ROLE_HR);

        $this->actingAs($hr->user)->post(route('employees.store'), $this->employeePayload([
            'name' => 'Direct Choice',
            'department' => 'Finance',
        ]))->assertRedirect();

        $this->assertDatabaseHas('employees', ['name' => 'Direct Choice', 'department' => 'Finance']);
    }

    public function test_the_configured_lists_are_sorted_and_free_of_duplicates(): void
    {
        $this->person()->update(['department' => 'Engineering']); // already configured

        $departments = Employee::departments();

        $this->assertSame(array_values(array_unique($departments)), $departments);
        $sorted = $departments;
        sort($sorted, SORT_NATURAL | SORT_FLAG_CASE);
        $this->assertSame($sorted, $departments);
    }

    // --------------------------------------------------------- L2 / L1

    public function test_hr_can_reach_the_announcements_screen(): void
    {
        $hr = $this->person(User::ROLE_HR);

        $this->actingAs($hr->user)->get(route('announcements.index'))->assertOk();
    }

    public function test_an_employee_cannot_reach_the_announcements_screen(): void
    {
        $staff = $this->person();

        $this->actingAs($staff->user)->get(route('announcements.index'))->assertForbidden();
    }

    public function test_hr_can_post_an_announcement(): void
    {
        $hr = $this->person(User::ROLE_HR);

        $this->actingAs($hr->user)->post(route('announcements.store'), [
            'title' => 'Office closed on Friday',
            'body' => 'The building is shut for maintenance.',
            'is_published' => '1',
        ])->assertRedirect(route('announcements.index'));

        $this->assertDatabaseHas('announcements', ['title' => 'Office closed on Friday']);
    }

    public function test_hr_can_delete_the_stale_payroll_announcement(): void
    {
        $hr = $this->person(User::ROLE_HR);
        // The exact row QA raised as L1.
        $stale = Announcement::create(['title' => 'Payroll date moved', 'body' => 'Salaries on the 1st.']);

        $this->actingAs($hr->user)->delete(route('announcements.destroy', $stale))->assertRedirect();

        $this->assertDatabaseMissing('announcements', ['id' => $stale->id]);
    }

    public function test_an_unpublished_announcement_is_off_the_dashboards(): void
    {
        Announcement::create(['title' => 'Live one', 'is_published' => true]);
        Announcement::create(['title' => 'Hidden one', 'is_published' => false]);

        $visible = Announcement::visible()->pluck('title')->all();

        $this->assertContains('Live one', $visible);
        $this->assertNotContains('Hidden one', $visible);
    }

    public function test_an_announcement_past_its_end_date_drops_off_by_itself(): void
    {
        Announcement::create(['title' => 'Still running', 'expires_at' => now()->addWeek()]);
        Announcement::create(['title' => 'Ends today', 'expires_at' => now()]);
        $finished = Announcement::create(['title' => 'Finished']);
        $finished->forceFill(['expires_at' => now()->subDay()])->saveQuietly();

        $visible = Announcement::visible()->pluck('title')->all();

        $this->assertContains('Still running', $visible);
        $this->assertContains('Ends today', $visible, 'the end date is the last day it shows');
        $this->assertNotContains('Finished', $visible);
    }

    public function test_the_dashboard_only_shows_visible_announcements(): void
    {
        $staff = $this->person();
        Announcement::create(['title' => 'Everyone should see this', 'is_published' => true]);
        Announcement::create(['title' => 'Draft nobody should see', 'is_published' => false]);

        $this->actingAs($staff->user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Everyone should see this')
            ->assertDontSee('Draft nobody should see');
    }

    public function test_an_end_date_in_the_past_is_rejected(): void
    {
        $hr = $this->person(User::ROLE_HR);

        $this->actingAs($hr->user)->post(route('announcements.store'), [
            'title' => 'Backdated',
            'expires_at' => now()->subWeek()->toDateString(),
        ])->assertSessionHasErrors('expires_at');
    }

    public function test_unticking_published_actually_unpublishes(): void
    {
        $hr = $this->person(User::ROLE_HR);
        $announcement = Announcement::create(['title' => 'Notice', 'is_published' => true]);

        // An unticked checkbox is simply absent from the request body.
        $this->actingAs($hr->user)
            ->patch(route('announcements.update', $announcement), ['title' => 'Notice'])
            ->assertRedirect();

        $this->assertFalse($announcement->fresh()->is_published);
    }

    // ------------------------------------------------------------- U6

    public function test_dates_use_one_format_everywhere(): void
    {
        $when = Carbon::parse('2026-11-08 15:30:00');

        $this->assertSame('08 Nov 2026', $when->asDate());
        $this->assertSame('03:30 PM', $when->asTime());
        $this->assertSame('08 Nov 2026, 03:30 PM', $when->asDateTime());
        $this->assertSame('Sun, 08 Nov 2026', $when->asDateWithDay());
    }

    public function test_no_view_formats_a_date_by_hand_any_more(): void
    {
        // Y-m-d, Y-m and Y-m-d\TH:i are machine values that <input type="date">,
        // month links and <input type="datetime-local"> require. F Y, l, D and
        // d are a month heading, a weekday and a day number — single parts,
        // not a rendering of a whole date.
        $allowed = ["'Y-m-d'", "'Y-m'", "'Y-m-d\\TH:i'", "'F Y'", "'l'", "'D'", "'d'", "'D, '", "'l, '"];
        $offenders = [];

        foreach (glob(resource_path('views/**/*.blade.php'), GLOB_BRACE) + glob(resource_path('views/*.blade.php')) as $file) {
            preg_match_all("/format\((\'[^\']+\')\)/", (string) file_get_contents($file), $matches);

            foreach ($matches[1] as $format) {
                if (! in_array($format, $allowed, true)) {
                    $offenders[] = basename($file) . ' => ' . $format;
                }
            }
        }

        $this->assertSame([], $offenders, 'views should use ->asDate()/->asDateTime() so the format is defined once');
    }

    // --------------------------------------------------------- U1 / U2 / U8

    public function test_list_tables_carry_the_stacking_class_and_labels(): void
    {
        $hr = $this->person(User::ROLE_HR);

        $this->actingAs($hr->user)->get(route('employees.index'))
            ->assertOk()
            ->assertSee('<table class="rt ', false)
            ->assertSee('data-label="Department"', false);
    }

    public function test_required_fields_are_marked_on_the_employee_form(): void
    {
        $hr = $this->person(User::ROLE_HR);

        $this->actingAs($hr->user)->get(route('employees.create'))
            ->assertOk()
            ->assertSee('Fields marked', false)
            ->assertSee('<span class="sr-only">(required)</span>', false);
    }

    public function test_no_screen_still_uses_the_browser_confirm_box(): void
    {
        $offenders = [];

        $views = array_merge(
            glob(resource_path('views/*.blade.php')),
            glob(resource_path('views/*/*.blade.php')),
            glob(resource_path('views/*/*/*.blade.php')),
        );

        foreach ($views as $file) {
            if (str_ends_with(str_replace('\\', '/', $file), 'components/confirm-action.blade.php')) {
                continue; // its own comment explains what it replaced
            }

            if (str_contains((string) file_get_contents($file), 'confirm(')) {
                $offenders[] = basename(dirname($file)) . '/' . basename($file);
            }
        }

        $this->assertSame([], $offenders, 'use <x-confirm-action> instead of onsubmit="return confirm(...)"');
    }

    public function test_the_delete_dialog_posts_to_the_right_place(): void
    {
        $hr = $this->person(User::ROLE_HR);
        $staff = $this->person();

        $this->actingAs($hr->user)->get(route('employees.index'))
            ->assertOk()
            ->assertSee(route('employees.destroy', $staff), false)
            ->assertSee('Delete this employee?', false);
    }

    // ------------------------------------------- attendance correction UI

    public function test_hr_sees_a_way_into_the_correction_form(): void
    {
        $hr = $this->person(User::ROLE_HR);
        $staff = $this->person();
        \App\Models\Attendance::create([
            'employee_id' => $staff->id,
            'date' => '2026-10-12',
            'clock_in' => '2026-10-12 09:00:00',
            'clock_out' => '2026-10-12 17:00:00',
            'break_minutes' => 30,
        ]);

        $this->actingAs($hr->user)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Correct attendance', false)
            ->assertSee('name="correction_note"', false);
    }

    // ------------------------------------------------------------ U10

    public function test_starting_a_break_marks_the_session_as_on_break(): void
    {
        $staff = $this->person();
        $session = \App\Models\Attendance::create([
            'employee_id' => $staff->id,
            'date' => today(),
            'clock_in' => now()->subHours(3),
        ]);

        $this->actingAs($staff->user)->post(route('attendance.break.start'))->assertRedirect();

        $this->assertTrue($session->fresh()->isOnBreak());
    }

    public function test_ending_a_break_adds_the_time_away_to_the_total(): void
    {
        $staff = $this->person();
        $session = \App\Models\Attendance::create([
            'employee_id' => $staff->id,
            'date' => today(),
            'clock_in' => now()->subHours(3),
            'break_minutes' => 10,
            'break_started_at' => now()->subMinutes(25),
        ]);

        $this->actingAs($staff->user)->post(route('attendance.break.end'))->assertRedirect();

        $session->refresh();
        $this->assertFalse($session->isOnBreak());
        $this->assertSame(35, $session->break_minutes, 'the 10 already taken plus 25 just now');
    }

    public function test_you_cannot_start_a_second_break(): void
    {
        $staff = $this->person();
        \App\Models\Attendance::create([
            'employee_id' => $staff->id,
            'date' => today(),
            'clock_in' => now()->subHours(3),
            'break_started_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($staff->user)->post(route('attendance.break.start'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_a_break_needs_a_session_to_belong_to(): void
    {
        $staff = $this->person();

        $this->actingAs($staff->user)->post(route('attendance.break.start'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_clocking_out_while_on_a_break_still_counts_the_break(): void
    {
        $staff = $this->person();
        $session = \App\Models\Attendance::create([
            'employee_id' => $staff->id,
            'date' => today(),
            'clock_in' => now()->subHours(8),
            'break_started_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($staff->user)->post(route('attendance.clockOut'))->assertRedirect();

        $session->refresh();
        $this->assertNotNull($session->clock_out);
        $this->assertFalse($session->isOnBreak(), 'the break should be closed with the day');
        $this->assertSame(30, $session->break_minutes, 'time away must not become productive time');
        $this->assertSame(450, $session->productive_minutes, '8h worked less a 30m break');
    }

    public function test_the_panel_offers_break_buttons_rather_than_a_minutes_box(): void
    {
        $staff = $this->person();
        \App\Models\Attendance::create([
            'employee_id' => $staff->id,
            'date' => today(),
            'clock_in' => now()->subHours(2),
        ]);

        $this->actingAs($staff->user)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Start Break')
            ->assertSee(route('attendance.break.start'), false);
    }

    public function test_the_panel_shows_a_running_break(): void
    {
        $staff = $this->person();
        \App\Models\Attendance::create([
            'employee_id' => $staff->id,
            'date' => today(),
            'clock_in' => now()->subHours(2),
            'break_started_at' => now()->subMinutes(12),
        ]);

        $this->actingAs($staff->user)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('On a break since')
            ->assertSee('End Break');
    }

    // ------------------------------------------------------------ U13

    public function test_the_profile_page_is_tabbed(): void
    {
        $staff = $this->person();

        $this->actingAs($staff->user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('role="tablist"', false)
            ->assertSee('Password')
            ->assertSee('Account');
    }

    public function test_a_non_admin_does_not_get_the_close_account_tab(): void
    {
        $staff = $this->person();

        $this->actingAs($staff->user)->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Close account');
    }

    public function test_an_admin_does_get_the_close_account_tab(): void
    {
        $admin = $this->person(User::ROLE_ADMIN);

        $this->actingAs($admin->user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Close account');
    }

    public function test_a_failed_password_change_reopens_the_password_tab(): void
    {
        $staff = $this->person();

        $this->actingAs($staff->user)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => 'definitely-wrong',
                'password' => 'NewPassword!2026',
                'password_confirmation' => 'NewPassword!2026',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->actingAs($staff->user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee("tab: 'password'", false);
    }

    // ------------------------------------------------------------- U9

    public function test_the_welcome_card_does_not_shout(): void
    {
        $admin = $this->person(User::ROLE_ADMIN);

        $response = $this->actingAs($admin->user)->get(route('dashboard'))->assertOk();

        $response->assertDontSee('HELLO ' . strtoupper($admin->user->name));
        $response->assertSee('Nothing is waiting for your decision today.');
    }

    public function test_the_welcome_card_does_not_offer_to_review_nothing(): void
    {
        $admin = $this->person(User::ROLE_ADMIN);

        $this->actingAs($admin->user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('>Review it<', false)
            ->assertSee('View leave requests');
    }

    public function test_the_employee_donut_states_the_headcount_in_text(): void
    {
        $admin = $this->person(User::ROLE_ADMIN);
        $this->person();
        $this->person();

        // Three people exist, and the figure must be readable without the chart.
        $this->actingAs($admin->user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('employees, split by designation', false);
    }

    // ------------------------------------------------------------- U7

    public function test_page_actions_all_use_the_same_button(): void
    {
        $hr = $this->person(User::ROLE_HR);

        foreach (['employees.index', 'goals.index', 'schedules.index', 'announcements.index', 'holidays.index'] as $route) {
            $this->actingAs($hr->user)->get(route($route))
                ->assertOk()
                ->assertSee('text-xs font-semibold uppercase tracking-wide', false);
        }
    }

    public function test_no_page_header_still_hardcodes_the_old_blue_button(): void
    {
        $hr = $this->person(User::ROLE_HR);

        foreach (['schedules.index', 'holidays.index'] as $route) {
            $this->actingAs($hr->user)->get(route($route))
                ->assertOk()
                ->assertDontSee('rounded-md text-white hover:opacity-90', false);
        }
    }

    // ------------------------------------------------------------ U12

    public function test_the_holiday_calendar_text_is_not_tiny_or_truncated(): void
    {
        $hr = $this->person(User::ROLE_HR);
        \App\Models\Holiday::create([
            'title' => 'Mahatma Gandhi Jayanti Public Holiday',
            'date' => now()->startOfMonth()->addDays(9),
        ]);

        $this->actingAs($hr->user)->get(route('holidays.index'))
            ->assertOk()
            ->assertSee('Mahatma Gandhi Jayanti Public Holiday')
            ->assertDontSee('text-[11px]', false)
            ->assertDontSee('px-1 py-0.5 truncate', false);
    }
}
