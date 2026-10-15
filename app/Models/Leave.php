<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    use HasFactory;
    use LogsActivity;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    /** Selectable leave types (value => label). */
    public const TYPES = [
        'paid' => 'Paid Leave',
        'unpaid' => 'Unpaid Leave',
        'special' => 'Birthday / Anniversary / Saturday Leave',
    ];

    /** Annual allowance (days) per leave type. */
    public const ALLOWANCES = [
        'paid' => 12,
        'unpaid' => 12,
        'special' => 1,
    ];

    /** Selectable leave sessions (value => label). */
    public const SESSIONS = [
        'full_day' => 'Full Day',
        'first_half' => 'Half Day - First Half',
        'second_half' => 'Half Day - Second Half',
    ];

    /**
     * Whether this leave is still awaiting a decision.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Limit to requests whose leave period has not finished yet, i.e. those
     * still worth acting on. A leave running today still counts; one whose
     * dates have fully passed does not.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('end_date', '>=', today());
    }

    /**
     * Is this a half-day leave (first or second half)?
     */
    public function isHalfDay(): bool
    {
        return in_array($this->session, ['first_half', 'second_half'], true);
    }

    /**
     * Human-readable session label, e.g. "Full Day".
     */
    public function sessionLabel(): string
    {
        return self::SESSIONS[$this->session] ?? ucfirst((string) $this->session);
    }

    /**
     * Human-readable leave-type label, e.g. "Paid Leave".
     */
    public function typeLabel(): string
    {
        return self::TYPES[$this->leave_type] ?? ucfirst((string) $this->leave_type);
    }

    /**
     * Number of leave days this request consumes.
     * Half-day sessions count as 0.5; otherwise it's the inclusive date span.
     */
    public function dayCount(): float
    {
        if ($this->isHalfDay()) {
            return 0.5;
        }

        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'leave_type',
        'session',
        'start_date',
        'end_date',
        'reason',
        'decision_note',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * The employee who requested this leave.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * e.g. "Paid Leave (18 Jun)".
     */
    public function activityLabel(): string
    {
        return $this->typeLabel() . ' (' . $this->start_date->format('d M') . ')';
    }

    /**
     * A leave's interesting moments are the request and the decision, not a
     * bare "updated".
     *
     * @return array{0: string, 1: string}
     */
    protected function activityEntry(string $verb): array
    {
        $actor = auth()->user()->name;
        $owner = $this->employee?->user?->name;

        if ($verb === 'created') {
            return ['leave.requested', $actor . ' requested ' . $this->activityLabel()];
        }

        if ($verb === 'updated' && $this->wasChanged('status')) {
            $whose = $owner ? $owner . "'s" : 'a';

            return ['leave.' . $this->status, $actor . ' ' . $this->status . ' ' . $whose . ' ' . $this->activityLabel()];
        }

        return $this->defaultActivityEntry($verb);
    }
}
