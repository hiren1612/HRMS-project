<?php

namespace Tests\Feature\Monitoring;

use App\Models\Employee;
use App\Models\Monitoring\LiveSession;
use App\Models\Monitoring\MonitoringAuditLog;
use App\Models\Monitoring\MonitoringPermission;
use App\Models\Monitoring\Screenshot;
use App\Models\Monitoring\TrackingDevice;
use App\Models\User;
use App\Services\Monitoring\TrackingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 6: looking at what was recorded.
 *
 * The screenshot gallery and the live viewer are the two most intrusive
 * screens in the module, so most of what is tested here is refusal: who
 * cannot open them, what cannot be reached by guessing a URL, and what is
 * written down about the people who can.
 */
class MonitoringViewingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('monitoring');
        config()->set('monitoring.enabled', true);
        config()->set('monitoring.live.enabled', true);
    }

    private function employee(string $name = 'Watched Person'): Employee
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

    private function device(Employee $employee, string $status = TrackingDevice::STATUS_APPROVED): TrackingDevice
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

    private function screenshot(Employee $employee, ?string $capturedAt = null): Screenshot
    {
        $path = 'screenshots/' . $employee->id . '/' . now()->toDateString() . '/' . fake()->uuid() . '.jpg';

        Storage::disk('monitoring')->put($path, 'fake-jpeg-bytes');

        return Screenshot::create([
            'employee_id' => $employee->id,
            'device_id' => $this->device($employee)->id,
            'captured_at' => $capturedAt ?? now(),
            'disk' => 'monitoring',
            'path' => $path,
            'width' => 1600,
            'height' => 900,
            'bytes' => 15,
            'status_at_capture' => 'active',
            'checksum' => hash('sha256', 'fake-jpeg-bytes'),
        ]);
    }

    // ------------------------------------------------------------ gallery

    public function test_the_gallery_needs_the_screenshots_capability(): void
    {
        // Monitoring access alone is not enough to look at someone's screen.
        $this->actingAs($this->viewer())
            ->get(route('monitoring.screenshots'))
            ->assertForbidden();

        $this->actingAs($this->viewer(['can_view_screenshots' => true]))
            ->get(route('monitoring.screenshots'))
            ->assertOk();
    }

    public function test_an_image_cannot_be_fetched_without_the_capability(): void
    {
        $shot = $this->screenshot($this->employee());

        // The file is on a private disk with no public URL, so this route is
        // the only way to the bytes — and it must refuse.
        $this->actingAs($this->viewer())
            ->get(route('monitoring.screenshots.show', $shot))
            ->assertForbidden();
    }

    public function test_an_ordinary_employee_cannot_fetch_an_image(): void
    {
        $employee = $this->employee();
        $shot = $this->screenshot($employee);

        // Not even their own: the gallery is not a self-service feature.
        $this->actingAs($employee->user)
            ->get(route('monitoring.screenshots.show', $shot))
            ->assertForbidden();
    }

    public function test_an_image_is_streamed_with_private_cache_headers(): void
    {
        $shot = $this->screenshot($this->employee());

        $response = $this->actingAs($this->viewer(['can_view_screenshots' => true]))
            ->get(route('monitoring.screenshots.show', $shot));

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        // A shared or proxied cache must not keep a copy of someone's screen.
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    public function test_a_row_whose_file_has_gone_is_a_404_not_an_error(): void
    {
        $shot = $this->screenshot($this->employee());

        Storage::disk('monitoring')->delete($shot->path);

        $this->actingAs($this->viewer(['can_view_screenshots' => true]))
            ->get(route('monitoring.screenshots.show', $shot))
            ->assertNotFound();
    }

    public function test_opening_the_gallery_is_audited_once_not_once_per_image(): void
    {
        $employee = $this->employee();

        for ($i = 0; $i < 5; $i++) {
            $this->screenshot($employee, now()->setTime(10, $i));
        }

        $user = $this->viewer(['can_view_screenshots' => true]);

        $this->actingAs($user)
            ->get(route('monitoring.screenshots', ['employee' => $employee->id]))
            ->assertOk();

        $logs = MonitoringAuditLog::where('action', MonitoringAuditLog::SCREENSHOT_VIEWED)->get();

        // One entry naming the day and the count, not five entries naming
        // nothing useful.
        $this->assertCount(1, $logs);
        $this->assertSame(5, $logs->first()->context['count']);
        $this->assertSame($employee->id, $logs->first()->employee_id);
    }

    public function test_an_empty_gallery_is_not_audited(): void
    {
        $this->actingAs($this->viewer(['can_view_screenshots' => true]))
            ->get(route('monitoring.screenshots'))
            ->assertOk();

        // Landing on an empty day is not an act worth recording.
        $this->assertDatabaseCount('employee_monitoring_audit_logs', 0);
    }

    public function test_a_screenshot_can_be_deleted_early(): void
    {
        $shot = $this->screenshot($this->employee());
        $path = $shot->path;

        $this->actingAs($this->viewer(['can_view_screenshots' => true]))
            ->delete(route('monitoring.screenshots.destroy', $shot))
            ->assertRedirect();

        Storage::disk('monitoring')->assertMissing($path);

        $this->assertDatabaseHas('employee_monitoring_audit_logs', [
            'action' => MonitoringAuditLog::SCREENSHOT_DELETED,
        ]);
    }

    // --------------------------------------------------------- live screen

    public function test_the_live_page_needs_the_live_capability(): void
    {
        $this->actingAs($this->viewer(['can_view_screenshots' => true]))
            ->get(route('monitoring.live'))
            ->assertForbidden();

        $this->actingAs($this->viewer(['can_view_live_screen' => true]))
            ->get(route('monitoring.live'))
            ->assertOk();
    }

    public function test_the_live_page_says_plainly_that_the_employee_will_know(): void
    {
        $this->actingAs($this->viewer(['can_view_live_screen' => true]))
            ->get(route('monitoring.live'))
            ->assertOk()
            ->assertSee('The employee will know');
    }

    public function test_requesting_a_live_session_creates_it_as_requested_only(): void
    {
        $employee = $this->employee();
        $this->device($employee);
        $user = $this->viewer(['can_view_live_screen' => true]);

        $this->actingAs($user)
            ->post(route('monitoring.live.store'), ['employee_id' => $employee->id])
            ->assertRedirect();

        $session = LiveSession::first();

        $this->assertNotNull($session);
        // Crucially NOT active: nothing may stream until the agent confirms
        // the indicator is on the employee's screen.
        $this->assertSame(LiveSession::STATUS_REQUESTED, $session->status);
        $this->assertNull($session->employee_notified_at);
        $this->assertSame($user->id, $session->initiated_by);

        $this->assertDatabaseHas('employee_monitoring_audit_logs', [
            'action' => MonitoringAuditLog::LIVE_REQUESTED,
            'user_id' => $user->id,
        ]);
    }

    public function test_an_employee_with_no_approved_device_cannot_be_watched(): void
    {
        $employee = $this->employee();
        $this->device($employee, TrackingDevice::STATUS_PENDING);

        $this->actingAs($this->viewer(['can_view_live_screen' => true]))
            ->post(route('monitoring.live.store'), ['employee_id' => $employee->id])
            ->assertSessionHasErrors('employee_id');

        $this->assertDatabaseCount('employee_live_monitoring_sessions', 0);
    }

    public function test_requesting_twice_does_not_create_a_second_session(): void
    {
        $employee = $this->employee();
        $this->device($employee);
        $user = $this->viewer(['can_view_live_screen' => true]);

        $this->actingAs($user)->post(route('monitoring.live.store'), ['employee_id' => $employee->id]);
        $this->actingAs($user)->post(route('monitoring.live.store'), ['employee_id' => $employee->id]);

        $this->assertDatabaseCount('employee_live_monitoring_sessions', 1);
    }

    public function test_live_viewing_cannot_be_requested_while_switched_off(): void
    {
        config()->set('monitoring.live.enabled', false);

        $employee = $this->employee();
        $this->device($employee);

        $this->actingAs($this->viewer(['can_view_live_screen' => true]))
            ->post(route('monitoring.live.store'), ['employee_id' => $employee->id])
            ->assertSessionHasErrors('employee_id');
    }

    public function test_the_frame_endpoint_reports_waiting_before_the_indicator_is_shown(): void
    {
        $employee = $this->employee();
        $device = $this->device($employee);

        $session = LiveSession::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'initiated_by' => $this->viewer()->id,
            'requested_at' => now(),
            'status' => LiveSession::STATUS_REQUESTED,
        ]);

        $this->actingAs($this->viewer(['can_view_live_screen' => true]))
            ->getJson(route('monitoring.live.frame', $session))
            ->assertOk()
            ->assertJsonPath('notified', false)
            ->assertJsonPath('frame', null)
            ->assertJsonPath('open', true);
    }

    public function test_the_frame_endpoint_returns_the_frame_the_agent_pushed(): void
    {
        $employee = $this->employee();
        $device = $this->device($employee);

        $session = LiveSession::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'initiated_by' => $this->viewer()->id,
            'requested_at' => now(),
            'started_at' => now(),
            'employee_notified_at' => now(),
            'status' => LiveSession::STATUS_ACTIVE,
        ]);

        Cache::put("monitoring.live.frame.{$session->id}", base64_encode('frame-bytes'), 15);

        $this->actingAs($this->viewer(['can_view_live_screen' => true]))
            ->getJson(route('monitoring.live.frame', $session))
            ->assertOk()
            ->assertJsonPath('notified', true)
            ->assertJsonPath('frame', base64_encode('frame-bytes'));
    }

    public function test_stopping_a_session_ends_it_and_drops_the_frame(): void
    {
        $employee = $this->employee();
        $device = $this->device($employee);

        $session = LiveSession::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'initiated_by' => $this->viewer()->id,
            'requested_at' => now(),
            'started_at' => now(),
            'employee_notified_at' => now(),
            'status' => LiveSession::STATUS_ACTIVE,
        ]);

        Cache::put("monitoring.live.frame.{$session->id}", 'x', 15);

        $this->actingAs($this->viewer(['can_view_live_screen' => true]))
            ->delete(route('monitoring.live.destroy', $session))
            ->assertRedirect();

        $session->refresh();

        $this->assertSame(LiveSession::STATUS_ENDED, $session->status);
        $this->assertSame(LiveSession::END_BY_ADMIN, $session->end_reason);
        $this->assertNull(Cache::get("monitoring.live.frame.{$session->id}"));
    }

    public function test_a_session_past_its_ceiling_is_closed_by_the_frame_poll(): void
    {
        config()->set('monitoring.live.max_duration_minutes', 30);

        $employee = $this->employee();
        $device = $this->device($employee);

        $session = LiveSession::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'initiated_by' => $this->viewer()->id,
            'requested_at' => now()->subHours(2),
            'started_at' => now()->subHours(2),
            'employee_notified_at' => now()->subHours(2),
            'status' => LiveSession::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->viewer(['can_view_live_screen' => true]))
            ->getJson(route('monitoring.live.frame', $session))
            ->assertOk()
            ->assertJsonPath('open', false);

        $this->assertSame(LiveSession::END_TIMEOUT, $session->fresh()->end_reason);
    }

    // ---------------------------------------------------------- retention

    public function test_prune_deletes_screenshots_past_their_retention(): void
    {
        $employee = $this->employee();

        app(TrackingSettings::class)->set('screenshots.retention_days', 30);

        $old = $this->screenshot($employee, now()->subDays(45));
        $recent = $this->screenshot($employee, now()->subDays(5));

        $this->artisan('monitoring:prune')->assertSuccessful();

        $this->assertDatabaseMissing('employee_screenshots', ['id' => $old->id]);
        $this->assertDatabaseHas('employee_screenshots', ['id' => $recent->id]);

        // The file must go with the row, or it sits on disk forever where
        // nothing will ever find it.
        Storage::disk('monitoring')->assertMissing($old->path);
        Storage::disk('monitoring')->assertExists($recent->path);
    }

    public function test_prune_dry_run_deletes_nothing(): void
    {
        $employee = $this->employee();

        app(TrackingSettings::class)->set('screenshots.retention_days', 30);

        $old = $this->screenshot($employee, now()->subDays(45));

        $this->artisan('monitoring:prune --dry-run')->assertSuccessful();

        $this->assertDatabaseHas('employee_screenshots', ['id' => $old->id]);
        Storage::disk('monitoring')->assertExists($old->path);
    }

    public function test_prune_also_removes_soft_deleted_rows_and_their_files(): void
    {
        $employee = $this->employee();

        app(TrackingSettings::class)->set('screenshots.retention_days', 30);

        $shot = $this->screenshot($employee, now()->subDays(45));
        $path = $shot->path;
        $shot->delete();   // soft delete leaves the file behind

        Storage::disk('monitoring')->assertExists($path);

        $this->artisan('monitoring:prune')->assertSuccessful();

        Storage::disk('monitoring')->assertMissing($path);
    }

    public function test_prune_refuses_a_nonsense_retention_period(): void
    {
        app(TrackingSettings::class)->set('screenshots.retention_days', 30);
        config()->set('monitoring.screenshots.retention_days', 0);

        // Deliberately not "delete everything": a zero here is far more
        // likely to be a mistake than an instruction.
        \App\Models\Monitoring\TrackingSetting::query()->delete();
        app(TrackingSettings::class)->flush();

        $this->artisan('monitoring:prune')->assertFailed();
    }

    // ------------------------------------------------------------ sidebar

    public function test_sidebar_links_follow_the_viewing_capabilities(): void
    {
        $plain = $this->viewer();

        $this->actingAs($plain)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('href="' . route('monitoring.screenshots') . '"', false)
            ->assertDontSee('href="' . route('monitoring.live') . '"', false);

        $full = $this->viewer(['can_view_screenshots' => true, 'can_view_live_screen' => true]);

        $this->actingAs($full)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="' . route('monitoring.screenshots') . '"', false)
            ->assertSee('href="' . route('monitoring.live') . '"', false);
    }
}
