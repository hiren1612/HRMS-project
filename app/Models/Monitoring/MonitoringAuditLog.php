<?php

namespace App\Models\Monitoring;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Who looked at what.
 *
 * Looking at somebody's screen is itself an event worth recording, so the
 * read side of this module is audited as carefully as the write side.
 * Rows are append-only: there is no update path, and $timestamps is off
 * because an audit entry that can be touched later is worth less.
 */
class MonitoringAuditLog extends Model
{
    protected $table = 'employee_monitoring_audit_logs';

    public const UPDATED_AT = null;

    public const SCREENSHOT_VIEWED = 'screenshot.viewed';
    public const SCREENSHOT_DOWNLOADED = 'screenshot.downloaded';
    public const SCREENSHOT_DELETED = 'screenshot.deleted';
    public const LIVE_REQUESTED = 'live.requested';
    public const LIVE_STARTED = 'live.started';
    public const LIVE_ENDED = 'live.ended';
    public const DEVICE_APPROVED = 'device.approved';
    public const DEVICE_REVOKED = 'device.revoked';
    public const SETTINGS_UPDATED = 'settings.updated';
    public const PERMISSION_CHANGED = 'permission.changed';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'employee_id',
        'action',
        'subject_type',
        'subject_id',
        'ip_address',
        'user_agent',
        'context',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Record an action. The request is read for the IP and user agent so
     * callers do not have to remember to pass them.
     *
     * @param  array<string, mixed>  $context
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        ?int $employeeId = null,
        array $context = [],
    ): self {
        $request = request();

        return self::create([
            'user_id' => auth()->id(),
            'employee_id' => $employeeId,
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => \Illuminate\Support\Str::limit((string) $request?->userAgent(), 500, ''),
            'context' => $context ?: null,
            'created_at' => now(),
        ]);
    }
}
