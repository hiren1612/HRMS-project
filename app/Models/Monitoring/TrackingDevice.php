<?php

namespace App\Models\Monitoring;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A PC running the desktop tracking agent.
 *
 * A device registers itself on first login and starts as "pending". Nothing
 * is tracked, captured or streamed until somebody approves it, so installing
 * the agent is never on its own enough to begin monitoring a person.
 */
class TrackingDevice extends Model
{
    protected $table = 'employee_tracking_devices';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REVOKED = 'revoked';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REVOKED,
    ];

    /**
     * Mirrors the column default, so a device that has just been registered
     * reads as pending in memory rather than having a null status.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'device_uuid',
        'device_name',
        'platform',
        'agent_version',
        'status',
        'approved_by',
        'approved_at',
        'last_seen_at',
        'last_ip',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TrackingSession::class, 'device_id');
    }

    public function screenshots(): HasMany
    {
        return $this->hasMany(Screenshot::class, 'device_id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * May this device send tracking data?
     *
     * When approval is switched off in settings a pending device is allowed
     * through, but a revoked one never is — revoking has to mean something.
     */
    public function isActive(bool $requireApproval = true): bool
    {
        if ($this->status === self::STATUS_REVOKED) {
            return false;
        }

        return $requireApproval
            ? $this->status === self::STATUS_APPROVED
            : true;
    }

    /**
     * Considered connected if the agent has been in touch recently.
     */
    public function isOnline(int $withinMinutes = 5): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subMinutes($withinMinutes));
    }
}
