<?php

namespace App\Models\Monitoring;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One live screen viewing, requested to finished.
 *
 * Every attempt is recorded, including refused and failed ones, so the
 * question "who watched whose screen, when, for how long" always has an
 * answer. A session cannot serve a frame until the agent has confirmed it
 * displayed the on-screen indicator.
 */
class LiveSession extends Model
{
    protected $table = 'employee_live_monitoring_sessions';

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_NOTIFIED = 'notified';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ENDED = 'ended';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_FAILED = 'failed';

    public const END_BY_ADMIN = 'stopped_by_admin';
    public const END_BY_EMPLOYEE = 'stopped_by_employee';
    public const END_DISCONNECTED = 'disconnected';
    public const END_TIMEOUT = 'timeout';

    /**
     * Mirrors the column default — see TrackingSession for why.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_REQUESTED,
    ];

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'device_id',
        'initiated_by',
        'requested_at',
        'employee_notified_at',
        'started_at',
        'ended_at',
        'status',
        'end_reason',
        'initiator_ip',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'employee_notified_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
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

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_REQUESTED,
            self::STATUS_NOTIFIED,
            self::STATUS_ACTIVE,
        ]);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [
            self::STATUS_REQUESTED,
            self::STATUS_NOTIFIED,
            self::STATUS_ACTIVE,
        ], true);
    }

    /**
     * Whether frames may be served.
     *
     * Requires the agent to have confirmed the indicator is on screen. If
     * that confirmation never arrives the session stays unviewable, which
     * is the intended failure mode: no indicator, no viewing.
     */
    public function mayStream(bool $requireNotification = true): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        return $requireNotification
            ? $this->employee_notified_at !== null
            : true;
    }

    /**
     * Past the maximum duration, so it should be closed regardless of what
     * the viewer does.
     */
    public function hasExpired(int $maxMinutes): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        $since = $this->started_at ?? $this->requested_at;

        return $since->lt(now()->subMinutes($maxMinutes));
    }

    public function durationSeconds(): int
    {
        if ($this->started_at === null) {
            return 0;
        }

        return (int) $this->started_at->diffInSeconds($this->ended_at ?? now());
    }
}
