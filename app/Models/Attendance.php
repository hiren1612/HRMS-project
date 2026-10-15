<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'date',
        'clock_in',
        'clock_out',
        'break_minutes',
        'break_started_at',
        'closed_by',
        'correction_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'break_minutes' => 'integer',
            'break_started_at' => 'datetime',
        ];
    }

    /**
     * The longest a single session is treated as real work (QA H1).
     * Anything beyond this is a forgotten clock-out, not a 97-hour shift.
     */
    public const MAX_SESSION_MINUTES = 720; // 12 hours

    public const CLOSED_MANUAL = 'manual';
    public const CLOSED_AUTO = 'auto';
    public const CLOSED_HR = 'hr';

    /**
     * A session longer than a plausible working day, i.e. someone forgot to
     * clock out. Shown as a warning so HR can correct the times.
     */
    public function isSuspicious(): bool
    {
        return $this->worked_minutes !== null
            && $this->worked_minutes > self::MAX_SESSION_MINUTES;
    }

    /**
     * Did the session end on a later day than it started? The attendance
     * tables show only a time, which hid the 97-hour rows (QA H1).
     */
    public function spansDays(): bool
    {
        return $this->clock_out !== null
            && ! $this->clock_in->isSameDay($this->clock_out);
    }

    /**
     * QA U10: is this person on a break right now?
     */
    public function isOnBreak(): bool
    {
        return $this->break_started_at !== null;
    }

    /**
     * Minutes elapsed in the break currently running (0 when not on one).
     */
    public function currentBreakMinutes(): int
    {
        return $this->isOnBreak()
            ? (int) $this->break_started_at->diffInMinutes(now())
            : 0;
    }

    /**
     * Total break time including a break still in progress, which is what
     * the panel should show while someone is away.
     */
    public function breakMinutesSoFar(): int
    {
        return $this->break_minutes + $this->currentBreakMinutes();
    }

    /**
     * The employee this attendance record belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * True while the employee is clocked in but hasn't clocked out yet.
     */
    public function isOpen(): bool
    {
        return $this->clock_out === null;
    }

    /**
     * Total worked minutes for a completed session (null if still open).
     */
    public function getWorkedMinutesAttribute(): ?int
    {
        if (! $this->clock_in || ! $this->clock_out) {
            return null;
        }

        return $this->clock_in->diffInMinutes($this->clock_out);
    }

    /**
     * Human-friendly worked duration, e.g. "8h 30m" (or "In progress").
     */
    public function workedLabel(): string
    {
        if ($this->isOpen()) {
            return 'In progress';
        }

        $minutes = $this->worked_minutes;

        return intdiv($minutes, 60) . 'h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) . 'm';
    }

    /**
     * Productive minutes for a completed session (null while still open).
     *
     * Formula:  productive = (clock_out - clock_in) - break_minutes
     * Floored at 0 so an over-long break can never produce a negative value.
     */
    public function getProductiveMinutesAttribute(): ?int
    {
        if ($this->isOpen()) {
            return null;
        }

        // Capped at a plausible day: a forgotten clock-out must not add 97
        // hours to a weekly total. isSuspicious() flags it for correction.
        $worked = min($this->worked_minutes, self::MAX_SESSION_MINUTES);

        return max(0, $worked - $this->break_minutes);
    }

    /**
     * Human-friendly productive duration, e.g. "7h 30m" (or "In progress").
     */
    public function productiveLabel(): string
    {
        if ($this->isOpen()) {
            return 'In progress';
        }

        $minutes = $this->productive_minutes;

        return intdiv($minutes, 60) . 'h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) . 'm';
    }

    public function activityLabel(): string
    {
        return $this->date?->format('d M Y') ?? ('#' . $this->getKey());
    }

    /**
     * Phrased as start/end of the working day rather than create/update.
     *
     * @return array{0: string, 1: string}
     */
    protected function activityEntry(string $verb): array
    {
        $actor = auth()->user()->name;

        if ($verb === 'created') {
            return ['attendance.started', $actor . ' started their day'];
        }

        if ($verb === 'updated' && $this->wasChanged('clock_out') && $this->clock_out !== null) {
            return ['attendance.ended', $actor . ' ended their day'];
        }

        if ($verb === 'updated' && $this->wasChanged('break_minutes')) {
            return ['attendance.break', $actor . ' recorded a break of ' . $this->break_minutes . ' min'];
        }

        return $this->defaultActivityEntry($verb);
    }
}
