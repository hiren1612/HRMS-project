<?php

namespace App\Models\Monitoring;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One bucket of activity, normally a minute.
 *
 * key_events is a COUNT of keypresses and nothing more. No key codes and no
 * typed characters are collected anywhere in this module. The count exists
 * to tell working from idle, which needs a number, not content.
 *
 * Unrelated to the HRMS audit trail in App\Models\Activity.
 */
class ActivityLog extends Model
{
    protected $table = 'employee_activity_logs';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_IDLE = 'idle';
    public const STATUS_PAUSED = 'paused';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_IDLE,
        self::STATUS_PAUSED,
    ];

    /**
     * Mirrors the column defaults — see TrackingSession for why.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'active_seconds' => 0,
        'idle_seconds' => 0,
        'mouse_events' => 0,
        'key_events' => 0,
    ];

    /** @var list<string> */
    protected $fillable = [
        'session_id',
        'employee_id',
        'client_uuid',
        'bucket_started_at',
        'bucket_ended_at',
        'active_seconds',
        'idle_seconds',
        'mouse_events',
        'key_events',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bucket_started_at' => 'datetime',
            'bucket_ended_at' => 'datetime',
            'active_seconds' => 'integer',
            'idle_seconds' => 'integer',
            'mouse_events' => 'integer',
            'key_events' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrackingSession::class, 'session_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function totalEvents(): int
    {
        return $this->mouse_events + $this->key_events;
    }
}
