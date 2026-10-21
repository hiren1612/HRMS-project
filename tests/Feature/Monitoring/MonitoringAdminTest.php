<?php

namespace Tests\Feature\Monitoring;

use App\Models\Employee;
use App\Models\Monitoring\MonitoringAuditLog;
use App\Models\Monitoring\MonitoringPermission;
use App\Models\Monitoring\TrackingDevice;
use App\Models\Monitoring\TrackingSession;
use App\Models\User;
use App\Services\Monitoring\TrackingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7: settings, device approval and who may see any of it.
 *
 * These three screens are where the module is actually governed, so the
 * tests are mostly about who is kept out: a capability is not a role, and
 * holding one capability must not quietly grant another.
 */
class MonitoringAdminTest extends TestCase
{
    use RefreshDatabase;

    private function employee(string $name = 'Test Person'): Employee
    {
        return Employee::create([
            'user_id' => User::factory()->create(['role' => User::ROLE_EMPLOYEE])->id,
            'name' => $name,
            'employee_code' => 'EMP-' . fake()->unique()->numerify('####'),
            'designation' => 'Developer',
            'department' => 'Engineering',
            'hire_date' => '2026-01-01',
            'status' => 'active',
        ]);
    }

    /** A user with monitoring access and the given capabilities. */
    private function viewer(array $capabilities = [], string $role = User::ROLE_ADMIN): User
    {
        $user = User::factory()->create(['role' => $role]);

        MonitoringPermission::create(array_merge([
            'user_id' => $user->id,
            'can_view_screenshots' => false,
            'can_view_live_screen' => false,
            'can_manage_devices' => false,
            'can_manage_settings' => false,
        ], $capabilities));

        return $user;
    }

    private function device(Employee $employee, string $status = TrackingDevice::STATUS_PENDING): TrackingDevice
    {
        return TrackingDevice::create([
            'employee_id' => $employee->id,
            'device_uuid' => fake()->uuid(),
            'device_name' => 'DESKTOP-TEST',
            'platform' => 'Windows 11',
            'status' => $status,
            'last_seen_at' => now(),
        ]);
    }

    // ------------------------------------------------------------- settings

    public function test_settings_need_the_settings_capability(): void
    {
        // Monitoring access alone is not enough to change what is recorded.
        $this->actingAs($this->viewer())
            ->get(route('monitoring.settings'))
            ->assertForbidden();

        $this->actingAs($this->viewer(['can_manage_settings' => true]))
            ->get(route('monitoring.settings'))
            ->assertOk()
            ->assertSee('Monitoring Settings');
    }

    public function test_settings_can_be_saved(): void
    {
        $user = $this->viewer(['can_manage_settings' => true]);

        $this->actingAs($user)->patch(route('monitoring.settings.update'), $this->formPayload([
            'enabled' => '1',
            'idle_threshold_minutes' => 7,
            'screenshots_enabled' => '1',
            'screenshots_interval_minutes' => 10,
        ]))->assertRedirect(route('monitoring.settings'));

        $settings = app(TrackingSettings::class);
        $settings->flush();

        $this->assertTrue($settings->isEnabled());
        $this->assertSame(7, $settings->int('idle_threshold_minutes'));
        $this->assertSame(10, $settings->int('screenshots.interval_minutes'));
    }

    public function test_an_unticked_checkbox_turns_the_setting_off(): void
    {
        $user = $this->viewer(['can_manage_settings' => true]);
        $settings = app(TrackingSettings::class);

        $settings->set('enabled', true, $user->id);
        $this->assertTrue($settings->isEnabled());

        // A checkbox sends nothing at all when unticked, which must read as
        // false rather than "leave it as it was".
        $this->actingAs($user)->patch(
            route('monitoring.settings.update'),
            $this->formPayload([], ['enabled']),
        )->assertRedirect();

        $settings->flush();
        $this->assertFalse($settings->isEnabled());
    }

    public function test_a_working_day_that_ends_before_it_starts_is_rejected(): void
    {
        $user = $this->viewer(['can_manage_settings' => true]);

        $this->actingAs($user)->patch(route('monitoring.settings.update'), $this->formPayload([
            'working_hours_start' => '18:00',
            'working_hours_end' => '09:00',
        ]))->assertSessionHasErrors('working_hours_start');
    }

    public function test_an_out_of_range_value_is_rejected(): void
    {
        $user = $this->viewer(['can_manage_settings' => true]);

        $this->actingAs($user)->patch(route('monitoring.settings.update'), $this->formPayload([
            'idle_threshold_minutes' => 999,     // max is 120
        ]))->assertSessionHasErrors('idle_threshold_minutes');
    }

    public function test_changing_a_setting_is_audited(): void
    {
        $user = $this->viewer(['can_manage_settings' => true]);

        $this->actingAs($user)->patch(route('monitoring.settings.update'), $this->formPayload([
            'enabled' => '1',
        ]));

        $this->assertDatabaseHas('employee_monitoring_audit_logs', [
            'user_id' => $user->id,
            'action' => MonitoringAuditLog::SETTINGS_UPDATED,
        ]);
    }

    public function test_an_unchanged_setting_is_not_audited(): void
    {
        $user = $this->viewer(['can_manage_settings' => true]);

        // Save the form twice without altering anything.
        $payload = $this->formPayload(['enabled' => '1']);
        $this->actingAs($user)->patch(route('monitoring.settings.update'), $payload);
        $before = MonitoringAuditLog::count();
        $this->actingAs($user)->patch(route('monitoring.settings.update'), $payload);

        $this->assertSame($before, MonitoringAuditLog::count(), 'a no-op save must not look like a change');
    }

    // -------------------------------------------------------------- devices

    public function test_devices_need_the_devices_capability(): void
    {
        $this->actingAs($this->viewer())
            ->get(route('monitoring.devices'))
            ->assertForbidden();

        $this->actingAs($this->viewer(['can_manage_devices' => true]))
            ->get(route('monitoring.devices'))
            ->assertOk();
    }

    public function test_a_device_can_be_approved(): void
    {
        $user = $this->viewer(['can_manage_devices' => true]);
        $device = $this->device($this->employee());

        $this->actingAs($user)
            ->patch(route('monitoring.devices.approve', $device))
            ->assertRedirect();

        $device->refresh();

        $this->assertSame(TrackingDevice::STATUS_APPROVED, $device->status);
        $this->assertSame($user->id, $device->approved_by);
        $this->assertNotNull($device->approved_at);

        $this->assertDatabaseHas('employee_monitoring_audit_logs', [
            'action' => MonitoringAuditLog::DEVICE_APPROVED,
            'user_id' => $user->id,
        ]);
    }

    public function test_revoking_destroys_the_token_and_closes_the_session(): void
    {
        $user = $this->viewer(['can_manage_devices' => true]);
        $employee = $this->employee();
        $device = $this->device($employee, TrackingDevice::STATUS_APPROVED);

        // The agent's live credential and an open day.
        $employee->user->createToken($device->device_uuid, ['agent']);

        $session = TrackingSession::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'started_at' => now()->subHours(2),
            'active_seconds' => 3600,
            'idle_seconds' => 0,
            'status' => TrackingSession::STATUS_RUNNING,
        ]);

        $this->actingAs($user)
            ->patch(route('monitoring.devices.revoke', $device), ['reason' => 'Laptop returned'])
            ->assertRedirect();

        $this->assertSame(TrackingDevice::STATUS_REVOKED, $device->fresh()->status);

        // A stolen machine must lose its credential now, not whenever it
        // next happens to make a request.
        $this->assertSame(0, $employee->user->tokens()->count());

        // And must not sit on the dashboard as somebody still at their desk.
        $this->assertSame(TrackingSession::STATUS_ENDED, $session->fresh()->status);
    }

    public function test_approving_an_already_approved_device_is_harmless(): void
    {
        $user = $this->viewer(['can_manage_devices' => true]);
        $device = $this->device($this->employee(), TrackingDevice::STATUS_APPROVED);

        $this->actingAs($user)->patch(route('monitoring.devices.approve', $device))->assertRedirect();

        $this->assertDatabaseMissing('employee_monitoring_audit_logs', [
            'action' => MonitoringAuditLog::DEVICE_APPROVED,
        ]);
    }

    public function test_the_devices_screen_filters_by_status(): void
    {
        $user = $this->viewer(['can_manage_devices' => true]);

        $pending = $this->employee('Pending Person');
        $this->device($pending, TrackingDevice::STATUS_PENDING);

        $approved = $this->employee('Approved Person');
        $this->device($approved, TrackingDevice::STATUS_APPROVED);

        $this->actingAs($user)->get(route('monitoring.devices', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Pending Person')
            ->assertDontSee('Approved Person');
    }

    // ---------------------------------------------------------- permissions

    public function test_permissions_are_admin_only_not_a_capability(): void
    {
        // Every monitoring capability, but not an administrator: still out.
        $powerful = $this->viewer([
            'can_view_screenshots' => true,
            'can_view_live_screen' => true,
            'can_manage_devices' => true,
            'can_manage_settings' => true,
        ], User::ROLE_HR);

        $this->actingAs($powerful)
            ->get(route('monitoring.permissions'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('monitoring.permissions'))
            ->assertOk();
    }

    public function test_an_ordinary_employee_cannot_reach_permissions(): void
    {
        $this->actingAs($this->employee()->user)
            ->get(route('monitoring.permissions'))
            ->assertForbidden();
    }

    public function test_access_can_be_granted(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $target = User::factory()->create(['role' => User::ROLE_HR]);

        $this->actingAs($admin)->post(route('monitoring.permissions.store'), [
            'user_id' => $target->id,
            'can_view_screenshots' => '1',
        ])->assertRedirect();

        $permission = MonitoringPermission::where('user_id', $target->id)->first();

        $this->assertNotNull($permission);
        $this->assertTrue($permission->can_view_screenshots);
        $this->assertFalse($permission->can_view_live_screen);
        $this->assertSame($admin->id, $permission->granted_by);

        // The target can now reach the overview; they could not before.
        $this->actingAs($target)->get(route('monitoring.overview'))->assertOk();
    }

    public function test_granting_twice_is_refused(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $target = User::factory()->create(['role' => User::ROLE_HR]);

        $this->actingAs($admin)->post(route('monitoring.permissions.store'), ['user_id' => $target->id]);
        $this->actingAs($admin)->post(route('monitoring.permissions.store'), ['user_id' => $target->id])
            ->assertSessionHasErrors('user_id');

        $this->assertSame(1, MonitoringPermission::where('user_id', $target->id)->count());
    }

    public function test_access_can_be_removed_and_takes_effect_at_once(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $target = $this->viewer([], User::ROLE_HR);
        $permission = MonitoringPermission::where('user_id', $target->id)->first();

        $this->actingAs($target)->get(route('monitoring.overview'))->assertOk();

        $this->actingAs($admin)->delete(route('monitoring.permissions.destroy', $permission))->assertRedirect();

        $this->actingAs($target)->get(route('monitoring.overview'))->assertForbidden();
    }

    public function test_a_self_grant_is_recorded_as_such(): void
    {
        // Not blocked — an administrator can write this row directly anyway,
        // and somebody has to be able to create the first one. But it must
        // be findable afterwards.
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post(route('monitoring.permissions.store'), [
            'user_id' => $admin->id,
            'can_view_screenshots' => '1',
        ])->assertRedirect();

        $log = MonitoringAuditLog::where('action', MonitoringAuditLog::PERMISSION_CHANGED)->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertTrue($log->context['self_grant']);
    }

    public function test_permission_changes_are_audited(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $target = $this->viewer([], User::ROLE_HR);
        $permission = MonitoringPermission::where('user_id', $target->id)->first();

        $this->actingAs($admin)->patch(route('monitoring.permissions.update', $permission), [
            'can_view_live_screen' => '1',
        ])->assertRedirect();

        $log = MonitoringAuditLog::where('action', MonitoringAuditLog::PERMISSION_CHANGED)->latest('id')->first();

        $this->assertSame('changed', $log->context['verb']);
        $this->assertFalse($log->context['from']['can_view_live_screen']);
        $this->assertTrue($log->context['to']['can_view_live_screen']);
    }

    // ------------------------------------------------------------- sidebar

    public function test_sidebar_links_follow_the_capabilities(): void
    {
        $plain = $this->viewer();

        $this->actingAs($plain)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('monitoring.overview'))
            ->assertDontSee(route('monitoring.devices'))
            ->assertDontSee(route('monitoring.settings'));

        $full = $this->viewer(['can_manage_devices' => true, 'can_manage_settings' => true]);

        $this->actingAs($full)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('monitoring.devices'))
            ->assertSee(route('monitoring.settings'));
    }

    /**
     * The settings form posts every field at once, so a partial test payload
     * would fail validation on the fields it left out.
     *
     * @param  array<string, mixed>  $overrides
     * @param  list<string>  $unticked  checkbox fields to omit entirely
     * @return array<string, mixed>
     */
    private function formPayload(array $overrides = [], array $unticked = []): array
    {
        $base = [
            'enabled' => '1',
            'idle_threshold_minutes' => 5,
            'session_stale_after_minutes' => 15,
            'screenshots_enabled' => '1',
            'screenshots_interval_minutes' => 5,
            'screenshots_retention_days' => 30,
            'live_enabled' => '1',
            'live_max_duration_minutes' => 30,
            'working_hours_enforce' => '1',
            'working_hours_start' => '09:00',
            'working_hours_end' => '19:00',
            'privacy_allow_employee_pause' => '1',
            'privacy_max_pause_minutes_per_day' => 60,
            'devices_require_approval' => '1',
            'devices_max_per_employee' => 3,
        ];

        foreach ($unticked as $field) {
            unset($base[$field]);
        }

        return array_merge($base, $overrides);
    }
}
