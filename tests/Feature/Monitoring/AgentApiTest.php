<?php

namespace Tests\Feature\Monitoring;

use App\Models\Employee;
use App\Models\Monitoring\ActivityLog;
use App\Models\Monitoring\IdlePeriod;
use App\Models\Monitoring\LiveSession;
use App\Models\Monitoring\Screenshot;
use App\Models\Monitoring\TrackingDevice;
use App\Models\Monitoring\TrackingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 4: the API the desktop agent talks to.
 *
 * The contract is whatever desktop-agent/tools/mock-server.js implements,
 * because the agent is already written and tested against that file. These
 * tests check the real server honours the same shapes, and — more
 * importantly — the rules the mock does not enforce: approval, consent
 * before streaming, working hours, and surviving a retry after a dropped
 * connection.
 */
class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-horse-battery';

    private const UUID = 'device-uuid-0001';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('monitoring');

        // monitoring.enabled is the master switch: screenshotsEnabled() and
        // liveEnabled() are both false without it, whatever their own flags
        // say. It ships off, so every test that sends data must turn it on.
        config()->set('monitoring.enabled', true);
        config()->set('monitoring.working_hours.enforce', false);
        config()->set('monitoring.screenshots.enabled', true);
        config()->set('monitoring.live.enabled', true);
    }

    private function employee(string $name = 'Agent Person'): Employee
    {
        return Employee::create([
            'user_id' => User::factory()->create([
                'role' => User::ROLE_EMPLOYEE,
                'password' => bcrypt(self::PASSWORD),
            ])->id,
            'name' => $name,
            'employee_code' => 'EMP-' . fake()->unique()->numerify('####'),
            'designation' => 'Software Developer',
            'department' => 'Engineering',
            'hire_date' => '2026-01-01',
            'status' => 'active',
        ]);
    }

    /** @return array{0: Employee, 1: string} */
    private function signedIn(string $status = TrackingDevice::STATUS_APPROVED): array
    {
        $employee = $this->employee();

        $response = $this->postJson('/api/agent/login', [
            'email' => $employee->user->email,
            'password' => self::PASSWORD,
            'device_uuid' => self::UUID,
            'device_name' => 'DESKTOP-TEST',
            'platform' => 'Windows 11',
            'agent_version' => '1.0.0',
        ])->assertOk();

        TrackingDevice::where('device_uuid', self::UUID)->update(['status' => $status]);

        return [$employee->fresh(), $response->json('token')];
    }

    private function asAgent(string $token): self
    {
        // Laravel keeps the resolved user on the guard between requests in a
        // single test, so a token deleted or a device revoked by an earlier
        // call would still appear valid to the next one. Each agent call is
        // a separate HTTP request in reality, so clear the guard to match.
        $this->app['auth']->forgetGuards();

        // Accept matters for the multipart uploads: without it a validation
        // failure redirects (302) instead of returning 422.
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);
    }

    // --------------------------------------------------------------- login

    public function test_login_registers_the_device_and_returns_a_token(): void
    {
        $employee = $this->employee();

        $response = $this->postJson('/api/agent/login', [
            'email' => $employee->user->email,
            'password' => self::PASSWORD,
            'device_uuid' => self::UUID,
            'device_name' => 'DESKTOP-TEST',
            'platform' => 'Windows 11',
            'agent_version' => '1.0.0',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'employee_code'], 'device' => ['status', 'requires_approval']]);

        $this->assertDatabaseHas('employee_tracking_devices', [
            'device_uuid' => self::UUID,
            'employee_id' => $employee->id,
            'device_name' => 'DESKTOP-TEST',
        ]);
    }

    public function test_a_new_device_starts_pending_when_approval_is_required(): void
    {
        config()->set('monitoring.devices.require_approval', true);

        $employee = $this->employee();

        $this->postJson('/api/agent/login', [
            'email' => $employee->user->email,
            'password' => self::PASSWORD,
            'device_uuid' => self::UUID,
            'device_name' => 'DESKTOP-TEST',
        ])->assertOk()->assertJsonPath('device.status', TrackingDevice::STATUS_PENDING);
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $employee = $this->employee();

        $this->postJson('/api/agent/login', [
            'email' => $employee->user->email,
            'password' => 'not-the-password',
            'device_uuid' => self::UUID,
            'device_name' => 'DESKTOP-TEST',
        ])->assertStatus(422);

        $this->assertDatabaseCount('employee_tracking_devices', 0);
    }

    public function test_an_account_with_no_employee_record_cannot_sign_in(): void
    {
        $user = User::factory()->create(['password' => bcrypt(self::PASSWORD)]);

        $this->postJson('/api/agent/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
            'device_uuid' => self::UUID,
            'device_name' => 'DESKTOP-TEST',
        ])->assertForbidden();
    }

    public function test_a_device_belonging_to_someone_else_is_refused(): void
    {
        [$first] = $this->signedIn();
        $second = $this->employee('Second Person');

        $this->postJson('/api/agent/login', [
            'email' => $second->user->email,
            'password' => self::PASSWORD,
            'device_uuid' => self::UUID,      // the same machine
            'device_name' => 'DESKTOP-TEST',
        ])->assertStatus(409);
    }

    public function test_signing_in_again_retires_the_previous_token(): void
    {
        [$employee, $firstToken] = $this->signedIn();

        $this->postJson('/api/agent/login', [
            'email' => $employee->user->email,
            'password' => self::PASSWORD,
            'device_uuid' => self::UUID,
            'device_name' => 'DESKTOP-TEST',
        ])->assertOk();

        // The old token must stop working, or a stolen laptop keeps reporting.
        $this->asAgent($firstToken)->getJson('/api/agent/session')->assertUnauthorized();
    }

    public function test_a_revoked_device_cannot_sign_in_again(): void
    {
        [$employee] = $this->signedIn(TrackingDevice::STATUS_REVOKED);

        $this->postJson('/api/agent/login', [
            'email' => $employee->user->email,
            'password' => self::PASSWORD,
            'device_uuid' => self::UUID,
            'device_name' => 'DESKTOP-TEST',
        ])->assertForbidden();
    }

    // ---------------------------------------------------------- authorisation

    public function test_every_endpoint_requires_a_token(): void
    {
        $this->getJson('/api/agent/session')->assertUnauthorized();
        $this->postJson('/api/agent/heartbeat')->assertUnauthorized();
        $this->postJson('/api/agent/activity')->assertUnauthorized();
        $this->postJson('/api/agent/screenshots')->assertUnauthorized();
    }

    public function test_a_pending_device_can_read_its_settings_but_not_send_data(): void
    {
        [, $token] = $this->signedIn(TrackingDevice::STATUS_PENDING);

        // It must be able to learn that it is pending, or the agent just
        // looks broken to the employee.
        $this->asAgent($token)->getJson('/api/agent/session')
            ->assertOk()
            ->assertJsonPath('device.status', TrackingDevice::STATUS_PENDING);

        $this->asAgent($token)->postJson('/api/agent/sessions/start')->assertForbidden();
        $this->asAgent($token)->postJson('/api/agent/activity', ['session_id' => 1])->assertForbidden();
    }

    public function test_a_revoked_device_is_told_to_sign_out(): void
    {
        [, $token] = $this->signedIn();

        TrackingDevice::where('device_uuid', self::UUID)->update(['status' => TrackingDevice::STATUS_REVOKED]);

        $this->asAgent($token)->getJson('/api/agent/session')
            ->assertForbidden()
            ->assertJsonPath('action', 'sign_out');
    }

    public function test_a_deleted_device_is_told_to_sign_out(): void
    {
        [, $token] = $this->signedIn();

        TrackingDevice::where('device_uuid', self::UUID)->delete();

        $this->asAgent($token)->getJson('/api/agent/session')
            ->assertUnauthorized()
            ->assertJsonPath('action', 'sign_out');
    }

    // -------------------------------------------------------------- sessions

    public function test_starting_and_ending_a_session(): void
    {
        [, $token] = $this->signedIn();

        $id = $this->asAgent($token)->postJson('/api/agent/sessions/start', ['started_at' => now()->toDateTimeString()])
            ->assertCreated()
            ->json('session_id');

        $this->assertDatabaseHas('employee_tracking_sessions', [
            'id' => $id,
            'status' => TrackingSession::STATUS_RUNNING,
        ]);

        $this->asAgent($token)->postJson('/api/agent/sessions/end', [
            'session_id' => $id,
            'reason' => TrackingSession::ENDED_LOGOUT,
        ])->assertOk();

        $this->assertDatabaseHas('employee_tracking_sessions', [
            'id' => $id,
            'status' => TrackingSession::STATUS_ENDED,
            'ended_reason' => TrackingSession::ENDED_LOGOUT,
        ]);
    }

    public function test_restarting_after_a_crash_resumes_the_open_session(): void
    {
        [, $token] = $this->signedIn();

        $first = $this->asAgent($token)->postJson('/api/agent/sessions/start')->json('session_id');

        // The agent crashed; on restart it asks for a session again. A second
        // row would leave an orphan open and double-count the day.
        $second = $this->asAgent($token)->postJson('/api/agent/sessions/start')
            ->assertOk()
            ->assertJsonPath('resumed', true)
            ->json('session_id');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('employee_tracking_sessions', 1);
    }

    public function test_ending_a_session_twice_is_not_an_error(): void
    {
        [, $token] = $this->signedIn();

        $id = $this->asAgent($token)->postJson('/api/agent/sessions/start')->json('session_id');

        $this->asAgent($token)->postJson('/api/agent/sessions/end', ['session_id' => $id])->assertOk();
        $this->asAgent($token)->postJson('/api/agent/sessions/end', ['session_id' => $id])->assertOk();
    }

    public function test_an_agent_cannot_end_another_devices_session(): void
    {
        [, $tokenA] = $this->signedIn();
        $idA = $this->asAgent($tokenA)->postJson('/api/agent/sessions/start')->json('session_id');

        $other = $this->employee('Other Person');
        $this->postJson('/api/agent/login', [
            'email' => $other->user->email,
            'password' => self::PASSWORD,
            'device_uuid' => 'device-uuid-0002',
            'device_name' => 'OTHER-PC',
        ]);
        TrackingDevice::where('device_uuid', 'device-uuid-0002')->update(['status' => TrackingDevice::STATUS_APPROVED]);
        $tokenB = $this->postJson('/api/agent/login', [
            'email' => $other->user->email,
            'password' => self::PASSWORD,
            'device_uuid' => 'device-uuid-0002',
            'device_name' => 'OTHER-PC',
        ])->json('token');

        $this->asAgent($tokenB)->postJson('/api/agent/sessions/end', ['session_id' => $idA])
            ->assertNotFound();
    }

    // -------------------------------------------------------------- activity

    public function test_activity_buckets_are_stored_and_totals_updated(): void
    {
        [, $token] = $this->signedIn();
        $id = $this->asAgent($token)->postJson('/api/agent/sessions/start')->json('session_id');

        $this->asAgent($token)->postJson('/api/agent/activity', [
            'session_id' => $id,
            'buckets' => [
                $this->bucket('b-1', '10:00', 60, 0),
                $this->bucket('b-2', '10:01', 30, 30),
            ],
            'idle_periods' => [
                ['startedAt' => '2026-10-19 11:00:00', 'endedAt' => '2026-10-19 11:20:00', 'seconds' => 1200],
            ],
        ])->assertOk()->assertJsonPath('accepted', 3);

        $this->assertDatabaseCount('employee_activity_logs', 2);
        $this->assertDatabaseCount('employee_idle_periods', 1);

        $this->assertDatabaseHas('employee_tracking_sessions', [
            'id' => $id,
            'active_seconds' => 90,
            'idle_seconds' => 30,
        ]);
    }

    public function test_resending_the_same_batch_does_not_double_count(): void
    {
        [, $token] = $this->signedIn();
        $id = $this->asAgent($token)->postJson('/api/agent/sessions/start')->json('session_id');

        $payload = [
            'session_id' => $id,
            'buckets' => [$this->bucket('b-1', '10:00', 60, 0)],
            'idle_periods' => [
                ['startedAt' => '2026-10-19 11:00:00', 'endedAt' => '2026-10-19 11:20:00', 'seconds' => 1200],
            ],
        ];

        // The agent re-sends after a dropped connection. This is normal.
        $this->asAgent($token)->postJson('/api/agent/activity', $payload)->assertOk();
        $this->asAgent($token)->postJson('/api/agent/activity', $payload)->assertOk();
        $this->asAgent($token)->postJson('/api/agent/activity', $payload)->assertOk();

        $this->assertDatabaseCount('employee_activity_logs', 1);
        $this->assertDatabaseCount('employee_idle_periods', 1);
        $this->assertDatabaseHas('employee_tracking_sessions', ['id' => $id, 'active_seconds' => 60]);
    }

    public function test_buckets_outside_working_hours_are_acknowledged_but_discarded(): void
    {
        config()->set('monitoring.working_hours.enforce', true);
        config()->set('monitoring.working_hours.start', '09:00');
        config()->set('monitoring.working_hours.end', '19:00');
        config()->set('monitoring.working_hours.days', [1, 2, 3, 4, 5, 6, 7]);

        [, $token] = $this->signedIn();
        $id = $this->asAgent($token)->postJson('/api/agent/sessions/start')->json('session_id');

        $this->asAgent($token)->postJson('/api/agent/activity', [
            'session_id' => $id,
            'buckets' => [
                $this->bucket('day', '10:00', 60, 0),
                $this->bucket('night', '23:30', 60, 0),
            ],
        ])->assertOk()
            // Both acknowledged, so the agent stops retrying the night one...
            ->assertJsonPath('accepted', 2)
            ->assertJsonPath('skipped_outside_hours', 1);

        // ...but only the daytime bucket is actually banked.
        $this->assertDatabaseCount('employee_activity_logs', 1);
        $this->assertDatabaseHas('employee_activity_logs', ['client_uuid' => 'day']);
        $this->assertDatabaseMissing('employee_activity_logs', ['client_uuid' => 'night']);
    }

    public function test_an_oversized_batch_is_rejected(): void
    {
        [, $token] = $this->signedIn();
        $id = $this->asAgent($token)->postJson('/api/agent/sessions/start')->json('session_id');

        $buckets = [];
        for ($i = 0; $i < 501; $i++) {
            $buckets[] = $this->bucket("b-{$i}", '10:00', 60, 0);
        }

        $this->asAgent($token)->postJson('/api/agent/activity', [
            'session_id' => $id,
            'buckets' => $buckets,
        ])->assertStatus(422);
    }

    // ----------------------------------------------------------- screenshots

    public function test_a_screenshot_is_stored_on_the_private_disk(): void
    {
        [$employee, $token] = $this->signedIn();
        $id = $this->asAgent($token)->postJson('/api/agent/sessions/start')->json('session_id');

        $this->asAgent($token)->post('/api/agent/screenshots', [
            'session_id' => $id,
            'captured_at' => '2026-10-19 10:05:00',
            'status_at_capture' => 'active',
            'width' => 1600,
            'height' => 900,
            'screenshot' => UploadedFile::fake()->image('screen.jpg', 1600, 900),
        ])->assertCreated();

        $shot = Screenshot::first();

        $this->assertNotNull($shot);
        $this->assertSame('monitoring', $shot->disk);
        $this->assertStringContainsString("screenshots/{$employee->id}/2026-10-19/", $shot->path);
        Storage::disk('monitoring')->assertExists($shot->path);

        // Nothing may be reachable from the public disk.
        $this->assertStringNotContainsString('public', $shot->disk);
    }

    public function test_a_retried_screenshot_upload_does_not_create_a_second_copy(): void
    {
        [, $token] = $this->signedIn();

        $payload = fn () => [
            'captured_at' => '2026-10-19 10:05:00',
            'status_at_capture' => 'active',
            'screenshot' => UploadedFile::fake()->image('screen.jpg'),
        ];

        $first = $this->asAgent($token)->post('/api/agent/screenshots', $payload())->assertCreated()->json('id');
        $second = $this->asAgent($token)->post('/api/agent/screenshots', $payload())
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->json('id');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('employee_screenshots', 1);
        $this->assertCount(1, Storage::disk('monitoring')->allFiles());
    }

    public function test_screenshots_are_refused_when_the_setting_is_off(): void
    {
        config()->set('monitoring.screenshots.enabled', false);

        [, $token] = $this->signedIn();

        $this->asAgent($token)->post('/api/agent/screenshots', [
            'captured_at' => '2026-10-19 10:05:00',
            'screenshot' => UploadedFile::fake()->image('screen.jpg'),
        ])->assertForbidden()->assertJsonPath('action', 'stop_screenshots');

        $this->assertDatabaseCount('employee_screenshots', 0);
    }

    public function test_a_non_image_upload_is_rejected(): void
    {
        [, $token] = $this->signedIn();

        $this->asAgent($token)->post('/api/agent/screenshots', [
            'captured_at' => '2026-10-19 10:05:00',
            'screenshot' => UploadedFile::fake()->create('payload.php', 10, 'text/x-php'),
        ])->assertStatus(422);
    }

    // ------------------------------------------------------------ live screen

    public function test_a_frame_is_refused_until_the_indicator_is_confirmed(): void
    {
        [$employee, $token] = $this->signedIn();
        $device = TrackingDevice::where('device_uuid', self::UUID)->first();

        $live = LiveSession::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'initiated_by' => $employee->user_id,
            'requested_at' => now(),
            'status' => LiveSession::STATUS_REQUESTED,
        ]);

        // This is the rule the whole design rests on.
        $this->asAgent($token)->post("/api/agent/live/{$live->id}/frame", [
            'frame' => UploadedFile::fake()->image('frame.jpg'),
        ])->assertForbidden()->assertJsonPath('action', 'stop_streaming');
    }

    public function test_frames_are_accepted_once_the_indicator_is_confirmed(): void
    {
        [$employee, $token] = $this->signedIn();
        $device = TrackingDevice::where('device_uuid', self::UUID)->first();

        $live = LiveSession::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'initiated_by' => $employee->user_id,
            'requested_at' => now(),
            'status' => LiveSession::STATUS_REQUESTED,
        ]);

        $this->asAgent($token)->postJson("/api/agent/live/{$live->id}/notified")->assertOk();

        $this->assertNotNull($live->fresh()->employee_notified_at);

        $this->asAgent($token)->post("/api/agent/live/{$live->id}/frame", [
            'frame' => UploadedFile::fake()->image('frame.jpg'),
        ])->assertOk();

        $this->assertSame(LiveSession::STATUS_ACTIVE, $live->fresh()->status);
    }

    public function test_ending_a_live_session_stops_further_frames(): void
    {
        [$employee, $token] = $this->signedIn();
        $device = TrackingDevice::where('device_uuid', self::UUID)->first();

        $live = LiveSession::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'initiated_by' => $employee->user_id,
            'requested_at' => now(),
            'status' => LiveSession::STATUS_REQUESTED,
        ]);

        $this->asAgent($token)->postJson("/api/agent/live/{$live->id}/notified")->assertOk();
        $this->asAgent($token)->post("/api/agent/live/{$live->id}/frame", ['frame' => UploadedFile::fake()->image('f.jpg')])->assertOk();
        $this->asAgent($token)->postJson("/api/agent/live/{$live->id}/end", ['reason' => LiveSession::END_BY_EMPLOYEE])->assertOk();

        $this->asAgent($token)->post("/api/agent/live/{$live->id}/frame", ['frame' => UploadedFile::fake()->image('f.jpg')])
            ->assertForbidden();
    }

    public function test_an_agent_cannot_push_frames_into_another_employees_session(): void
    {
        [$victim] = $this->signedIn();
        $victimDevice = TrackingDevice::where('device_uuid', self::UUID)->first();

        $live = LiveSession::create([
            'employee_id' => $victim->id,
            'device_id' => $victimDevice->id,
            'initiated_by' => $victim->user_id,
            'requested_at' => now(),
            'status' => LiveSession::STATUS_NOTIFIED,
            'employee_notified_at' => now(),
        ]);

        $attacker = $this->employee('Attacker');
        $token = $this->postJson('/api/agent/login', [
            'email' => $attacker->user->email,
            'password' => self::PASSWORD,
            'device_uuid' => 'attacker-uuid',
            'device_name' => 'ATTACKER-PC',
        ])->json('token');
        TrackingDevice::where('device_uuid', 'attacker-uuid')->update(['status' => TrackingDevice::STATUS_APPROVED]);

        $this->asAgent($token)->post("/api/agent/live/{$live->id}/frame", ['frame' => UploadedFile::fake()->image('f.jpg')])
            ->assertNotFound();
    }

    // -------------------------------------------------------------- heartbeat

    public function test_the_heartbeat_returns_settings_and_no_instruction_by_default(): void
    {
        [, $token] = $this->signedIn();
        $id = $this->asAgent($token)->postJson('/api/agent/sessions/start')->json('session_id');

        $this->asAgent($token)->postJson('/api/agent/heartbeat', [
            'session_id' => $id,
            'status' => 'active',
            'active_seconds' => 120,
            'idle_seconds' => 0,
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('live', null)
            ->assertJsonStructure(['settings' => ['tracking_enabled', 'idle_threshold_seconds', 'screenshots_enabled']]);
    }

    public function test_the_heartbeat_carries_a_pending_live_request(): void
    {
        [$employee, $token] = $this->signedIn();
        $device = TrackingDevice::where('device_uuid', self::UUID)->first();

        $live = LiveSession::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'initiated_by' => $employee->user_id,
            'requested_at' => now(),
            'status' => LiveSession::STATUS_REQUESTED,
        ]);

        $this->asAgent($token)->postJson('/api/agent/heartbeat', [
            'status' => 'active',
            'active_seconds' => 60,
            'idle_seconds' => 0,
        ])->assertOk()->assertJsonPath('live.id', $live->id);
    }

    // ------------------------------------------------------------------ misc

    public function test_logout_revokes_only_the_current_token(): void
    {
        [, $token] = $this->signedIn();

        $this->asAgent($token)->postJson('/api/agent/logout')->assertOk();
        $this->asAgent($token)->getJson('/api/agent/session')->assertUnauthorized();
    }

    public function test_login_is_rate_limited(): void
    {
        $employee = $this->employee();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/agent/login', [
                'email' => $employee->user->email,
                'password' => 'wrong',
                'device_uuid' => self::UUID,
                'device_name' => 'X',
            ]);
        }

        $this->postJson('/api/agent/login', [
            'email' => $employee->user->email,
            'password' => 'wrong',
            'device_uuid' => self::UUID,
            'device_name' => 'X',
        ])->assertStatus(429);
    }

    /** @return array<string, mixed> */
    private function bucket(string $uuid, string $time, int $active, int $idle): array
    {
        $start = Carbon::parse("2026-10-19 {$time}:00");

        return [
            'client_uuid' => $uuid,
            'bucket_started_at' => $start->toDateTimeString(),
            'bucket_ended_at' => $start->copy()->addMinute()->toDateTimeString(),
            'active_seconds' => $active,
            'idle_seconds' => $idle,
            'mouse_events' => 0,
            'key_events' => 0,
            'status' => 'active',
        ];
    }
}
