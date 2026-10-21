<?php

namespace Tests\Feature\Monitoring;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Monitoring\ActivityLog;
use App\Models\Monitoring\LiveSession;
use App\Models\Monitoring\MonitoringAuditLog;
use App\Models\Monitoring\Screenshot;
use App\Models\Monitoring\TrackingDevice;
use App\Models\Monitoring\TrackingSession;
use App\Models\Monitoring\TrackingSetting;
use App\Models\User;
use App\Services\Monitoring\TrackingSettings;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Phase 2 of the Employee Productivity module: schema, models and settings.
 *
 * The point of this file is as much about what the module must NOT do —
 * track without approval, bank time outside working hours, stream without
 * the employee being told, or disturb existing attendance — as what it does.
 */
class TrackingFoundationTest extends TestCase
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

    private function device(?Employee $staff = null, string $status = TrackingDevice::STATUS_APPROVED): TrackingDevice
    {
        return TrackingDevice::create([
            'employee_id' => ($staff ?? $this->person())->id,
            'device_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'device_name' => 'DESKTOP-TEST',
            'platform' => 'Windows 11',
            'agent_version' => '1.0.0',
            'status' => $status,
        ]);
    }

    private function settings(): TrackingSettings
    {
        $settings = app(TrackingSettings::class);
        $settings->flush();

        return $settings;
    }

    // ------------------------------------------------- the module is inert

    public function test_monitoring_is_switched_off_until_somebody_turns_it_on(): void
    {
        $this->assertFalse($this->settings()->isEnabled());
        $this->assertFalse($this->settings()->screenshotsEnabled());
        $this->assertFalse($this->settings()->liveEnabled());
    }

    public function test_screenshots_stay_off_while_the_master_switch_is_off(): void
    {
        $settings = $this->settings();
        $settings->set('screenshots.enabled', true);

        // The sub-switch is on, but the module as a whole is not.
        $this->assertTrue($settings->get('screenshots.enabled'));
        $this->assertFalse($settings->screenshotsEnabled());
    }

    // ------------------------------------------------------------ settings

    public function test_a_setting_falls_back_to_its_config_default(): void
    {
        $this->assertSame(
            config('monitoring.idle_threshold_minutes'),
            $this->settings()->int('idle_threshold_minutes')
        );
    }

    public function test_an_override_wins_over_the_default_and_keeps_its_type(): void
    {
        $settings = $this->settings();

        $settings->set('idle_threshold_minutes', 7);
        $settings->set('devices.require_approval', false);

        $this->assertSame(7, $settings->get('idle_threshold_minutes'));
        $this->assertIsInt($settings->get('idle_threshold_minutes'));
        $this->assertFalse($settings->get('devices.require_approval'));
        $this->assertIsBool($settings->get('devices.require_approval'));
    }

    public function test_a_setting_outside_the_editable_list_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // Not editable from the web interface by design.
        $this->settings()->set('privacy.excluded_windows', []);
    }

    public function test_changing_a_setting_is_audited_with_before_and_after(): void
    {
        $admin = $this->person(User::ROLE_ADMIN);

        $this->actingAs($admin->user);
        $this->settings()->set('idle_threshold_minutes', 9);

        $entry = MonitoringAuditLog::where('action', MonitoringAuditLog::SETTINGS_UPDATED)->sole();

        $this->assertSame($admin->user->id, $entry->user_id);
        $this->assertSame('idle_threshold_minutes', $entry->context['key']);
        $this->assertSame(5, $entry->context['from']);
        $this->assertSame(9, $entry->context['to']);
    }

    public function test_an_override_takes_effect_without_waiting_for_a_cache_to_expire(): void
    {
        $settings = $this->settings();

        $this->assertSame(5, $settings->int('idle_threshold_minutes')); // warms the cache
        $settings->set('idle_threshold_minutes', 11);

        $this->assertSame(11, $settings->int('idle_threshold_minutes'));
    }

    // ------------------------------------------------------------- devices

    public function test_a_new_device_is_pending_and_may_not_track(): void
    {
        $device = $this->device(null, TrackingDevice::STATUS_PENDING);

        $this->assertFalse($device->isActive(requireApproval: true));
    }

    public function test_an_approved_device_may_track(): void
    {
        $this->assertTrue($this->device()->isActive(requireApproval: true));
    }

    public function test_a_revoked_device_may_never_track_even_without_approval_required(): void
    {
        $device = $this->device(null, TrackingDevice::STATUS_REVOKED);

        // Turning approval off is a convenience, not a way back in.
        $this->assertFalse($device->isActive(requireApproval: false));
    }

    public function test_the_same_pc_cannot_register_twice(): void
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $staff = $this->person();

        TrackingDevice::create([
            'employee_id' => $staff->id, 'device_uuid' => $uuid, 'device_name' => 'PC',
        ]);

        $this->expectException(QueryException::class);

        TrackingDevice::create([
            'employee_id' => $staff->id, 'device_uuid' => $uuid, 'device_name' => 'PC again',
        ]);
    }

    // ------------------------------------------------------------ sessions

    public function test_a_session_reports_active_idle_and_a_percentage(): void
    {
        $device = $this->device();
        $session = TrackingSession::create([
            'employee_id' => $device->employee_id,
            'device_id' => $device->id,
            'started_at' => now()->subHours(8),
            'active_seconds' => 6 * 3600,
            'idle_seconds' => 2 * 3600,
        ]);

        $this->assertSame(8 * 3600, $session->trackedSeconds());
        $this->assertSame(75, $session->activityPercent());
        $this->assertSame('6h 00m', $session->activeLabel());
        $this->assertSame('2h 00m', $session->idleLabel());
    }

    public function test_a_session_with_no_tracked_time_does_not_divide_by_zero(): void
    {
        $device = $this->device();
        $session = TrackingSession::create([
            'employee_id' => $device->employee_id,
            'device_id' => $device->id,
            'started_at' => now(),
        ]);

        $this->assertSame(0, $session->activityPercent());
    }

    public function test_a_session_the_agent_stopped_reporting_on_is_stale(): void
    {
        $device = $this->device();
        $session = TrackingSession::create([
            'employee_id' => $device->employee_id,
            'device_id' => $device->id,
            'started_at' => now()->subHours(6),
        ]);

        $this->assertTrue($session->isStale(afterMinutes: 15));
    }

    public function test_a_session_still_reporting_is_not_stale(): void
    {
        $device = $this->device();
        $session = TrackingSession::create([
            'employee_id' => $device->employee_id,
            'device_id' => $device->id,
            'started_at' => now()->subHours(6),
        ]);

        ActivityLog::create([
            'session_id' => $session->id,
            'employee_id' => $session->employee_id,
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'bucket_started_at' => now()->subMinutes(2),
            'bucket_ended_at' => now()->subMinute(),
            'active_seconds' => 60,
        ]);

        $this->assertFalse($session->isStale(afterMinutes: 15));
    }

    // ------------------------------------------------- duplicate submissions

    public function test_a_replayed_activity_bucket_is_rejected(): void
    {
        $device = $this->device();
        $session = TrackingSession::create([
            'employee_id' => $device->employee_id,
            'device_id' => $device->id,
            'started_at' => now(),
        ]);

        $uuid = (string) \Illuminate\Support\Str::uuid();
        $bucket = [
            'session_id' => $session->id,
            'employee_id' => $session->employee_id,
            'client_uuid' => $uuid,
            'bucket_started_at' => now()->subMinute(),
            'bucket_ended_at' => now(),
            'active_seconds' => 60,
        ];

        ActivityLog::create($bucket);

        // A retry after a dropped connection must not double-count the minute.
        $this->expectException(QueryException::class);
        ActivityLog::create($bucket);
    }

    public function test_a_replayed_screenshot_upload_is_rejected(): void
    {
        $device = $this->device();
        $at = now()->startOfSecond();

        $shot = [
            'employee_id' => $device->employee_id,
            'device_id' => $device->id,
            'captured_at' => $at,
            'path' => 'a/b.jpg',
        ];

        Screenshot::create($shot);

        $this->expectException(QueryException::class);
        Screenshot::create($shot);
    }

    // ------------------------------------------------------- working hours

    public function test_activity_outside_working_hours_is_out_of_bounds(): void
    {
        $settings = $this->settings();

        // Tuesday 14:00 is inside 09:00-19:00, Mon-Fri.
        $this->assertTrue($settings->withinWorkingHours(Carbon::parse('2026-11-03 14:00')));

        // Same day, late evening.
        $this->assertFalse($settings->withinWorkingHours(Carbon::parse('2026-11-03 22:30')));

        // Sunday, middle of the day.
        $this->assertFalse($settings->withinWorkingHours(Carbon::parse('2026-11-08 14:00')));
    }

    public function test_working_hours_can_be_switched_off(): void
    {
        $settings = $this->settings();
        $settings->set('working_hours.enforce', false);

        $this->assertTrue($settings->withinWorkingHours(Carbon::parse('2026-11-08 03:00')));
    }

    // ------------------------------------------------------------- privacy

    public function test_a_password_manager_window_is_excluded_from_capture(): void
    {
        $settings = $this->settings();

        $this->assertTrue($settings->isWindowExcluded('Bitwarden - Vault'));
        $this->assertTrue($settings->isWindowExcluded('My Vault', 'keepass.exe'));
        $this->assertFalse($settings->isWindowExcluded('Invoice.xlsx - Excel'));
        $this->assertFalse($settings->isWindowExcluded(null));
    }

    public function test_a_screenshot_never_leaks_its_storage_path_through_json(): void
    {
        $device = $this->device();
        $shot = Screenshot::create([
            'employee_id' => $device->employee_id,
            'device_id' => $device->id,
            'captured_at' => now(),
            'path' => 'monitoring/2026/secret.jpg',
            'thumbnail_path' => 'monitoring/2026/secret-thumb.jpg',
        ]);

        $json = $shot->toJson();

        $this->assertStringNotContainsString('secret.jpg', $json);
        $this->assertStringNotContainsString('monitoring/2026', $json);
    }

    public function test_the_agent_payload_never_includes_anything_secret(): void
    {
        $payload = $this->settings()->agentPayload();

        $this->assertArrayHasKey('idle_threshold_seconds', $payload);
        $this->assertSame(300, $payload['idle_threshold_seconds']);
        $this->assertArrayHasKey('excluded_windows', $payload);

        // The agent is handed configuration, never credentials or paths.
        // Checked against the keys rather than the whole blob, because a
        // legitimate value here is the name "1Password".
        foreach (array_keys($payload) as $key) {
            foreach (['password', 'secret', 'token', 'key', 'credential'] as $forbidden) {
                $this->assertStringNotContainsStringIgnoringCase($forbidden, $key);
            }
        }

        // And no filesystem path leaks through a value.
        foreach ($payload as $value) {
            if (is_string($value)) {
                $this->assertStringNotContainsString('storage/', $value);
                $this->assertStringNotContainsString(base_path(), $value);
            }
        }
    }

    // --------------------------------------------------------- live screen

    public function test_a_live_session_cannot_stream_before_the_employee_is_told(): void
    {
        $device = $this->device();
        $admin = $this->person(User::ROLE_ADMIN);

        $live = LiveSession::create([
            'employee_id' => $device->employee_id,
            'device_id' => $device->id,
            'initiated_by' => $admin->user->id,
            'requested_at' => now(),
            'status' => LiveSession::STATUS_ACTIVE,
        ]);

        $this->assertFalse($live->mayStream(requireNotification: true));

        $live->update(['employee_notified_at' => now()]);

        $this->assertTrue($live->fresh()->mayStream(requireNotification: true));
    }

    public function test_a_live_session_expires_on_its_own(): void
    {
        $device = $this->device();
        $admin = $this->person(User::ROLE_ADMIN);

        $live = LiveSession::create([
            'employee_id' => $device->employee_id,
            'device_id' => $device->id,
            'initiated_by' => $admin->user->id,
            'requested_at' => now()->subHours(2),
            'started_at' => now()->subHours(2),
            'status' => LiveSession::STATUS_ACTIVE,
        ]);

        $this->assertTrue($live->hasExpired(maxMinutes: 30));
    }

    // ----------------------------------- existing functionality is untouched

    public function test_the_module_does_not_touch_the_attendance_table(): void
    {
        $staff = $this->person();
        $device = $this->device($staff);

        Attendance::create([
            'employee_id' => $staff->id,
            'date' => today(),
            'clock_in' => now()->subHours(8),
            'clock_out' => now(),
            'break_minutes' => 30,
        ]);

        $before = Attendance::sole();

        $session = TrackingSession::create([
            'employee_id' => $staff->id,
            'device_id' => $device->id,
            'started_at' => now()->subHours(8),
            'active_seconds' => 3600,
            'idle_seconds' => 600,
        ]);
        $session->update(['status' => TrackingSession::STATUS_ENDED, 'ended_at' => now()]);

        $after = Attendance::sole();

        $this->assertSame(1, Attendance::count(), 'tracking must not create attendance rows');
        $this->assertEquals($before->clock_in, $after->clock_in);
        $this->assertEquals($before->clock_out, $after->clock_out);
        $this->assertSame($before->break_minutes, $after->break_minutes);
        $this->assertSame($before->productive_minutes, $after->productive_minutes);
    }

    public function test_removing_an_employee_takes_their_tracking_data_with_them(): void
    {
        $staff = $this->person();
        $device = $this->device($staff);
        TrackingSession::create([
            'employee_id' => $staff->id,
            'device_id' => $device->id,
            'started_at' => now(),
        ]);

        $staff->delete();

        $this->assertSame(0, TrackingDevice::count());
        $this->assertSame(0, TrackingSession::count());
    }

    public function test_an_audit_entry_survives_the_account_that_created_it(): void
    {
        $admin = $this->person(User::ROLE_ADMIN);
        $this->actingAs($admin->user);

        MonitoringAuditLog::record(MonitoringAuditLog::SCREENSHOT_VIEWED, null, null, ['n' => 1]);

        $admin->user->delete();

        $entry = MonitoringAuditLog::sole();
        $this->assertNull($entry->user_id, 'the actor is cleared, but the record stays');
        $this->assertSame(MonitoringAuditLog::SCREENSHOT_VIEWED, $entry->action);
    }

    public function test_every_new_table_is_namespaced_and_none_replaced_an_existing_one(): void
    {
        $existing = [
            'users', 'employees', 'attendances', 'leaves', 'holidays', 'goals',
            'schedules', 'announcements', 'activities', 'notifications',
        ];

        foreach ($existing as $table) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Schema::hasTable($table),
                "existing table [{$table}] must still exist"
            );
        }

        foreach ([
            'employee_tracking_devices', 'employee_tracking_sessions',
            'employee_activity_logs', 'employee_idle_periods',
            'employee_screenshots', 'employee_live_monitoring_sessions',
            'employee_tracking_settings', 'employee_monitoring_audit_logs',
            'employee_monitoring_permissions',
        ] as $table) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Schema::hasTable($table),
                "new table [{$table}] should have been created"
            );
        }
    }

    public function test_no_keystroke_content_column_exists_anywhere(): void
    {
        // key_events is a count. If a column ever appears that could hold what
        // somebody typed, this test should be the thing that objects.
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('employee_activity_logs');

        foreach ($columns as $column) {
            $this->assertStringNotContainsString('keystroke', $column);
            $this->assertStringNotContainsString('keylog', $column);
            $this->assertStringNotContainsString('text', $column);
            $this->assertStringNotContainsString('content', $column);
        }

        $this->assertContains('key_events', $columns);
    }
}
