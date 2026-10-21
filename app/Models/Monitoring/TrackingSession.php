<?php

namespace App\Models\Monitoring;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One run of the desktop agent, from start of tracking to the end of it.
 *
 * This is NOT attendance. The existing `attendances` table, clock in/out,
 * worked hours and leave balances are untouched by this module: an agent
 * session is a record of time at a PC, which is a different claim from a
 * record of a working day.
 */
class TrackingSession extends Model
{
    protected $table = 'employee_tracking_sessions';

    public const STATUS_RUNNING = 'running';
    public const STATUS_ENDED = 'ended';

    public const ENDED_MANUAL = 'manual';
    public const ENDED_LOGOUT = 'logout';
    public const ENDED_SHUTDOWN = 'shutdown';
    public const ENDED_STALE = 'stale';

    /**
     * Mirrors of the column defaults.
     *
     * A database default is not applied to the in-memory model, so without
     * this a freshly created session has a null status and reads as "not
     * running" until it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_RUNNING,
        'active_seconds' => 0,
        'idle_seconds' => 0,
    ];

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'device_id',
        'started_at',
        'ended_at',
        'active_seconds',
        'idle_seconds',
        'status',
        'ended_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'active_seconds' => 'integer',
            'idle_seconds' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(TrackingDevice::class, 'device_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'session_id');
    }

    public function idlePeriods(): HasMany
    {
        return $this->hasMany(IdlePeriod::class, 'session_id');
    }

    public function screenshots(): HasMany
    {
        return $this->hasMany(Screenshot::class, 'session_id');
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    public function scopeForDay(Builder $query, \DateTimeInterface $day): Builder
    {
        return $query->whereDate('started_at', $day);
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * Recompute this session's totals from the activity buckets it owns.
     *
     * The agent also reports running totals on each heartbeat, but the
     * buckets are the detail the dashboard charts, so the two must agree.
     * Summing the stored rows means a bucket that was rejected for falling
     * outside working hours is excluded from the headline figure too,
     * rather than the session claiming time the timeline cannot show.
     */
    public function refreshTotalsFromLogs(): void
    {
        $totals = $this->activityLogs()
            ->selectRaw('COALESCE(SUM(active_seconds), 0) AS active, COALESCE(SUM(idle_seconds), 0) AS idle')
            ->first();

        $this->forceFill([
            'active_seconds' => (int) ($totals->active ?? 0),
            'idle_seconds' => (int) ($totals->idle ?? 0),
        ])->save();
    }

    /**
     * Active plus idle. Not the wall-clock gap between start and end: an
     * agent that was shut down for an hour should not claim that hour.
     */
    public function trackedSeconds(): int
    {
        return $this->active_seconds + $this->idle_seconds;
    }

    /**
     * Share of tracked time that was active, 0-100.
     */
    public function activityPercent(): int
    {
        $tracked = $this->trackedSeconds();

        return $tracked > 0
            ? (int) round($this->active_seconds / $tracked * 100)
            : 0;
    }

    /**
     * A session the agent stopped reporting on, e.g. after a crash or a
     * power cut. The server closes these rather than letting them run on.
     */
    public function isStale(int $afterMinutes): bool
    {
        if (! $this->isRunning()) {
            return false;
        }

        $lastContact = $this->activityLogs()->max('bucket_ended_at');
        $reference = $lastContact ? \Illuminate\Support\Carbon::parse($lastContact) : $this->started_at;

        return $reference->lt(now()->subMinutes($afterMinutes));
    }

    public static function formatSeconds(int $seconds): string
    {
        return intdiv($seconds, 3600) . 'h ' . str_pad((string) (intdiv($seconds, 60) % 60), 2, '0', STR_PAD_LEFT) . 'm';
    }

    public function activeLabel(): string
    {
        return self::formatSeconds($this->active_seconds);
    }

    public function idleLabel(): string
    {
        return self::formatSeconds($this->idle_seconds);
    }
}
