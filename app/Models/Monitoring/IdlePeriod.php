<?php

namespace App\Models\Monitoring;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single stretch of inactivity, from the moment the idle threshold was
 * crossed to the moment the employee came back.
 *
 * The session totals answer "how much idle time today". This answers "when,
 * and for how long", which is what the daily timeline is drawn from.
 */
class IdlePeriod extends Model
{
    protected $table = 'employee_idle_periods';

    /** @var list<string> */
    protected $fillable = [
        'session_id',
        'employee_id',
        'started_at',
        'ended_at',
        'seconds',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'seconds' => 'integer',
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

    /**
     * Still idle right now.
     */
    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    public function label(): string
    {
        return TrackingSession::formatSeconds($this->seconds);
    }
}
