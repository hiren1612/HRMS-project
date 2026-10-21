<?php

namespace App\Services\Monitoring;

use App\Models\Monitoring\MonitoringAuditLog;
use App\Models\Monitoring\TrackingSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves monitoring settings: a database override if an administrator has
 * set one, otherwise the default from config/monitoring.php.
 *
 * Keys are dot paths into that config file, e.g. "screenshots.interval_minutes".
 * The resolved set is cached because the agent asks for it on every sync;
 * any write clears the cache, so a change takes effect on the next request
 * rather than whenever the cache happens to expire.
 */
class TrackingSettings
{
    private const CACHE_KEY = 'monitoring.settings';
    private const CACHE_SECONDS = 300;

    /**
     * Settings an administrator is allowed to change, with the rule used to
     * validate a new value. Anything not listed here can only be changed by
     * editing config/monitoring.php, which keeps the dangerous switches out
     * of reach of the web interface.
     *
     * @var array<string, string>
     */
    public const EDITABLE = [
        'enabled' => 'boolean',
        'idle_threshold_minutes' => 'integer|min:1|max:120',
        'session_stale_after_minutes' => 'integer|min:5|max:240',

        'screenshots.enabled' => 'boolean',
        'screenshots.interval_minutes' => 'integer|min:1|max:120',
        'screenshots.retention_days' => 'integer|min:1|max:365',

        'live.enabled' => 'boolean',
        'live.max_duration_minutes' => 'integer|min:1|max:240',

        'working_hours.enforce' => 'boolean',
        'working_hours.start' => 'date_format:H:i',
        'working_hours.end' => 'date_format:H:i',

        'privacy.allow_employee_pause' => 'boolean',
        'privacy.max_pause_minutes_per_day' => 'integer|min:0|max:480',

        'devices.require_approval' => 'boolean',
        'devices.max_per_employee' => 'integer|min:1|max:20',
    ];

    /**
     * A setting value, falling back to the config default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $overrides = $this->overrides();

        if (array_key_exists($key, $overrides)) {
            return $overrides[$key];
        }

        return config('monitoring.' . $key, $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function string(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }

    /**
     * Store an override and clear the cache.
     *
     * @throws \InvalidArgumentException when the key is not editable
     */
    public function set(string $key, mixed $value, ?int $userId = null): void
    {
        if (! array_key_exists($key, self::EDITABLE)) {
            throw new \InvalidArgumentException("[{$key}] is not an editable monitoring setting.");
        }

        $previous = $this->get($key);

        TrackingSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => $userId ?? auth()->id()],
        );

        $this->flush();

        MonitoringAuditLog::record(MonitoringAuditLog::SETTINGS_UPDATED, null, null, [
            'key' => $key,
            'from' => $previous,
            'to' => $value,
        ]);
    }

    /**
     * Every editable setting with its current value, for the settings form.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $resolved = [];

        foreach (array_keys(self::EDITABLE) as $key) {
            $resolved[$key] = $this->get($key);
        }

        return $resolved;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    // ------------------------------------------------------------------
    // Convenience readers used across the module
    // ------------------------------------------------------------------

    public function isEnabled(): bool
    {
        return $this->bool('enabled');
    }

    public function screenshotsEnabled(): bool
    {
        return $this->isEnabled() && $this->bool('screenshots.enabled');
    }

    public function liveEnabled(): bool
    {
        return $this->isEnabled() && $this->bool('live.enabled');
    }

    public function requiresDeviceApproval(): bool
    {
        return $this->bool('devices.require_approval', true);
    }

    public function requiresNotificationBeforeStreaming(): bool
    {
        return (bool) config('monitoring.live.require_employee_notification', true);
    }

    /**
     * Is the given moment inside configured working hours?
     *
     * Used to discard activity and skip captures outside work, so the module
     * cannot quietly accumulate a picture of somebody's evenings.
     */
    public function withinWorkingHours(?Carbon $moment = null): bool
    {
        if (! $this->bool('working_hours.enforce', true)) {
            return true;
        }

        $moment ??= now();

        $days = (array) config('monitoring.working_hours.days', [1, 2, 3, 4, 5]);

        if (! in_array($moment->dayOfWeekIso, $days, true)) {
            return false;
        }

        $start = $this->string('working_hours.start', '09:00');
        $end = $this->string('working_hours.end', '19:00');

        return $moment->format('H:i') >= $start && $moment->format('H:i') <= $end;
    }

    /**
     * Should a capture be skipped because of what is on screen?
     */
    public function isWindowExcluded(?string $windowTitle, ?string $processName = null): bool
    {
        $haystack = strtolower(trim($windowTitle . ' ' . $processName));

        if ($haystack === '') {
            return false;
        }

        foreach ((array) config('monitoring.privacy.excluded_windows', []) as $needle) {
            if ($needle !== '' && str_contains($haystack, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The payload the desktop agent needs to configure itself.
     *
     * @return array<string, mixed>
     */
    public function agentPayload(): array
    {
        return [
            'tracking_enabled' => $this->isEnabled(),
            'idle_threshold_seconds' => $this->int('idle_threshold_minutes', 5) * 60,
            'activity_bucket_seconds' => (int) config('monitoring.activity_bucket_seconds', 60),
            'screenshots_enabled' => $this->screenshotsEnabled(),
            'screenshot_interval_seconds' => $this->int('screenshots.interval_minutes', 5) * 60,
            'screenshot_max_dimension' => (int) config('monitoring.screenshots.max_dimension', 1600),
            'screenshot_jpeg_quality' => (int) config('monitoring.screenshots.jpeg_quality', 60),
            'live_enabled' => $this->liveEnabled(),
            'excluded_windows' => array_values((array) config('monitoring.privacy.excluded_windows', [])),
            'allow_pause' => $this->bool('privacy.allow_employee_pause', true),
            'max_pause_minutes_per_day' => $this->int('privacy.max_pause_minutes_per_day', 60),
            'working_hours' => [
                'enforce' => $this->bool('working_hours.enforce', true),
                'start' => $this->string('working_hours.start', '09:00'),
                'end' => $this->string('working_hours.end', '19:00'),
                'days' => array_values((array) config('monitoring.working_hours.days', [1, 2, 3, 4, 5])),
            ],
        ];
    }

    /**
     * Database overrides, keyed by setting name.
     *
     * @return array<string, mixed>
     */
    private function overrides(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function (): array {
            // During a request before the table exists (a fresh clone running
            // migrate for the first time) fall back to config alone.
            if (! \Illuminate\Support\Facades\Schema::hasTable('employee_tracking_settings')) {
                return [];
            }

            return TrackingSetting::query()
                ->pluck('value', 'key')
                ->all();
        });
    }
}
