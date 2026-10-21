<?php

namespace Tests\Feature\Monitoring;

use App\Models\Employee;
use App\Models\Monitoring\ActivityLog;
use App\Models\Monitoring\IdlePeriod;
use App\Models\Monitoring\MonitoringPermission;
use App\Models\Monitoring\TrackingDevice;
use App\Models\Monitoring\TrackingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Phase 5: the admin-facing productivity screens.
 *
 * The access tests matter more than the display tests here. These pages show
 * how long named colleagues sat at their desks, so the question "who can open
 * this" has to be answered by something stricter than the role column.
 */
class MonitoringDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function employee(string $name = 'Test Person', string $role = User::ROLE_EMPLOYEE): Employee
    {
        return Employee::create([
            'user_id' => User::factory()->create(['role' => $role])->id,
            'name' => $name,
            'employee_code' => 'EMP-' . fake()->unique()->numerify('####'),
            'designation' => 'Software Developer',
            'department' => 'Engineering',
            'hire_date' => '2026-01-01',
            'status' => 'active',
        ]);
    }

    /** A user who is allowed to see monitoring data. */
    private function viewer(array $capabilities = []): User
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

        MonitoringPermission::create(array_merge([
            'user_id' => $user->id,
            'can_view_screenshots' => false,
            'can_view_live_screen' => false,
            'can_manage_devices' => false,
            'can_manage_settings' => false,
        ], $capabilities));

        return $user;
    }

    private function device(Employee $employee, string $status = 'approved'): TrackingDevice
    {
        return TrackingDevice::create([
            'employee_id' => $employee->id,
            'device_uuid' => fake()->uuid(),
            'device_name' => 'DESKTOP-TEST',
            'platform' => 'Windows 11',
            'agent_version' => '1.0.0',
            'status' => $status,
            'last_seen_at' => now(),
        ]);
    }

    private function trackingSession(Employee $employee, TrackingDevice $device, int $active, int $idle, string $status = 'ended'): TrackingSession
    {
        return TrackingSession::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'started_at' => now()->startOfDay()->addHours(9),
            'ended_at' => $status === 'running' ? null : now()->startOfDay()->addHours(17),
            'active_seconds' => $active,
            'idle_seconds' => $idle,
            'status' => $status,
        ]);
    }

    // ------------------------------------------------------------- access

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get(route('monitoring.overview'))->assertRedirect(route('login'));
    }

    public function test_an_ordinary_employee_is_refused(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee->user)
            ->get(route('monitoring.overview'))
            ->assertForbidden();
    }

    public function test_an_administrator_without_a_permission_row_is_refused(): void
    {
        // The point of the whole middleware: running the HRMS is not the same
        // as being cleared to watch colleagues.
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('monitoring.overview'))
            ->assertForbidden();
    }

    public function test_a_user_with_a_permission_row_may_open_the_overview(): void
    {
        $this->actingAs($this->viewer())
            ->get(route('monitoring.overview'))
            ->assertOk()
            ->assertSee('Productivity Overview');
    }

    public function test_the_sidebar_link_is_hidden_without_permission(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        // Matched as a full href, not a bare substring: /monitoring is a
        // prefix of /monitoring/permissions, which an administrator is meant
        // to see even with no monitoring access of their own — that is how
        // the first permission row ever gets created.
        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('href="' . route('monitoring.overview') . '"', false)
            ->assertDontSee('href="' . route('monitoring.index') . '"', false);
    }

    public function test_an_administrator_without_access_can_still_reach_the_grant_screen(): void
    {
        // The bootstrap path: with no permission rows at all, somebody has
        // to be able to create the first one.
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="' . route('monitoring.permissions') . '"', false);

        $this->actingAs($admin)->get(route('monitoring.permissions'))->assertOk();
    }

    public function test_the_sidebar_link_appears_with_permission(): void
    {
        $this->actingAs($this->viewer())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('monitoring.overview'));
    }

    public function test_the_sidebar_link_follows_the_permission_not_the_role(): void
    {
        // Somebody on the employee role who has nonetheless been cleared to
        // see monitoring data. The route lets them in, so the link has to
        // show too: access and navigation must not disagree.
        $employee = $this->employee('Cleared Employee');

        MonitoringPermission::create([
            'user_id' => $employee->user->id,
            'can_view_screenshots' => false,
            'can_view_live_screen' => false,
            'can_manage_devices' => false,
            'can_manage_settings' => false,
        ]);

        $this->actingAs($employee->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('monitoring.overview'));

        $this->actingAs($employee->user)
            ->get(route('monitoring.overview'))
            ->assertOk();
    }

    // ------------------------------------------------------------ overview

    public function test_the_overview_totals_the_day(): void
    {
        $employee = $this->employee('Priya Sharma');
        $device = $this->device($employee);

        // 6h active, 2h idle.
        $this->trackingSession($employee, $device, 21600, 7200);

        $this->actingAs($this->viewer())
            ->get(route('monitoring.overview'))
            ->assertOk()
            ->assertSee('6h 0m')      // active
            ->assertSee('2h 0m')      // idle
            ->assertSee('8h 0m')      // tracked
            ->assertSee('Priya Sharma');
    }

    public function test_sessions_for_one_employee_are_summed_not_listed_twice(): void
    {
        // Signed out at lunch and back in afterwards: two rows, one person.
        $employee = $this->employee('Split Day');
        $device = $this->device($employee);

        $this->trackingSession($employee, $device, 7200, 0);
        $this->trackingSession($employee, $device, 10800, 0);

        $this->actingAs($this->viewer())
            ->get(route('monitoring.index'))
            ->assertOk()
            ->assertSee('5h 0m');
    }

    public function test_an_employee_with_a_device_but_no_data_is_flagged(): void
    {
        $employee = $this->employee('Silent Agent');
        $this->device($employee);

        $this->actingAs($this->viewer())
            ->get(route('monitoring.overview'))
            ->assertOk()
            ->assertSee('Silent Agent')
            ->assertSee('No data');
    }

    public function test_pending_devices_are_surfaced(): void
    {
        $employee = $this->employee('Needs Approval');
        $this->device($employee, 'pending');

        $this->actingAs($this->viewer())
            ->get(route('monitoring.overview'))
            ->assertOk()
            ->assertSee('Devices awaiting approval')
            ->assertSee('Needs Approval');
    }

    public function test_a_day_with_no_data_does_not_divide_by_zero(): void
    {
        $this->actingAs($this->viewer())
            ->get(route('monitoring.overview'))
            ->assertOk()
            ->assertSee('0%');
    }

    // ---------------------------------------------------------------- list

    public function test_the_list_shows_every_employee_including_untracked_ones(): void
    {
        $tracked = $this->employee('Has Data');
        $this->trackingSession($tracked, $this->device($tracked), 3600, 0);

        $this->employee('No Data At All');

        $this->actingAs($this->viewer())
            ->get(route('monitoring.index'))
            ->assertOk()
            ->assertSee('Has Data')
            ->assertSee('No Data At All');
    }

    public function test_the_list_can_be_searched(): void
    {
        $this->employee('Findable Person');
        $this->employee('Other Person');

        $this->actingAs($this->viewer())
            ->get(route('monitoring.index', ['search' => 'Findable']))
            ->assertOk()
            ->assertSee('Findable Person')
            ->assertDontSee('Other Person');
    }

    // -------------------------------------------------------------- detail

    public function test_the_detail_page_shows_one_employees_day(): void
    {
        $employee = $this->employee('Detailed Person');
        $device = $this->device($employee);
        $session = $this->trackingSession($employee, $device, 14400, 1800);

        IdlePeriod::create([
            'session_id' => $session->id,
            'employee_id' => $employee->id,
            'started_at' => now()->startOfDay()->addHours(13),
            'ended_at' => now()->startOfDay()->addHours(13)->addMinutes(30),
            'seconds' => 1800,
        ]);

        $this->actingAs($this->viewer())
            ->get(route('monitoring.show', $employee))
            ->assertOk()
            ->assertSee('Detailed Person')
            ->assertSee('4h 0m')          // active
            ->assertSee('30m')            // idle
            ->assertSee('DESKTOP-TEST');  // the device
    }

    public function test_the_detail_page_explains_an_empty_day(): void
    {
        $employee = $this->employee('Nothing Today');

        $this->actingAs($this->viewer())
            ->get(route('monitoring.show', $employee))
            ->assertOk()
            ->assertSee('Nothing was reported')
            ->assertSee('no tracking device registered');
    }

    public function test_the_hourly_timeline_places_activity_in_the_right_hour(): void
    {
        $employee = $this->employee('Hourly Person');
        $device = $this->device($employee);
        $session = $this->trackingSession($employee, $device, 3600, 0);

        ActivityLog::create([
            'session_id' => $session->id,
            'employee_id' => $employee->id,
            'client_uuid' => fake()->uuid(),
            'bucket_started_at' => now()->startOfDay()->addHours(14),
            'bucket_ended_at' => now()->startOfDay()->addHours(14)->addMinute(),
            'active_seconds' => 60,
            'idle_seconds' => 0,
            'status' => 'active',
        ]);

        $this->actingAs($this->viewer())
            ->get(route('monitoring.show', $employee))
            ->assertOk()
            ->assertSee('14:00 — 1m active, — idle');
    }

    // ----------------------------------------------------------- date handling

    public function test_a_past_day_can_be_viewed(): void
    {
        $employee = $this->employee('Yesterday Worker');
        $device = $this->device($employee);

        $yesterday = now()->subDay();

        TrackingSession::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'started_at' => $yesterday->copy()->startOfDay()->addHours(9),
            'ended_at' => $yesterday->copy()->startOfDay()->addHours(17),
            'active_seconds' => 3600,
            'idle_seconds' => 0,
            'status' => 'ended',
        ]);

        // Today shows nothing for them...
        $this->actingAs($this->viewer())
            ->get(route('monitoring.overview'))
            ->assertOk()
            ->assertSee('No data');

        // ...yesterday does.
        $this->actingAs($this->viewer())
            ->get(route('monitoring.overview', ['day' => $yesterday->toDateString()]))
            ->assertOk()
            ->assertSee('1h 0m');
    }

    public function test_a_nonsense_date_falls_back_to_today_instead_of_erroring(): void
    {
        $this->actingAs($this->viewer())
            ->get(route('monitoring.overview', ['day' => 'not-a-date']))
            ->assertOk();
    }

    public function test_a_future_date_is_clamped_to_today(): void
    {
        $future = now()->addMonth()->toDateString();

        $this->actingAs($this->viewer())
            ->get(route('monitoring.overview', ['day' => $future]))
            ->assertOk()
            // The date input must not be left showing a future day.
            ->assertSee('value="' . now()->toDateString() . '"', false);
    }

    // ------------------------------------------------------- no side effects

    public function test_opening_the_screens_changes_no_tracking_data(): void
    {
        $employee = $this->employee();
        $device = $this->device($employee);
        $session = $this->trackingSession($employee, $device, 3600, 600);

        $before = $session->fresh()->toArray();

        $viewer = $this->viewer();
        $this->actingAs($viewer)->get(route('monitoring.overview'))->assertOk();
        $this->actingAs($viewer)->get(route('monitoring.index'))->assertOk();
        $this->actingAs($viewer)->get(route('monitoring.show', $employee))->assertOk();

        $this->assertSame($before, $session->fresh()->toArray(), 'viewing must not alter the session');
    }
}
