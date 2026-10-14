<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;
    use LogsActivity;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    /**
     * Mass-assignable attributes.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'employee_code',
        'phone',
        'address',
        'designation',
        'department',
        'manager_id',
        'salary',
        'carry_forward',
        'hire_date',
        'date_of_birth',
        'status',
    ];

    /**
     * Attribute casting so dates/decimals come back as the right PHP types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'date_of_birth' => 'date',
            'salary' => 'decimal:2',
            'carry_forward' => 'decimal:1',
        ];
    }

    /**
     * The user account this employee profile belongs to (inverse of User::employee).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * All leave requests submitted by this employee.
     */
    /**
     * The user this employee reports to (a manager/HR/admin account).
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Total productive minutes across completed sessions in a date range.
     *
     * Summed in PHP through Attendance::$productive_minutes rather than in
     * SQL. The previous raw query used MySQL-only GREATEST()/TIMESTAMPDIFF(),
     * which broke on any other database, and duplicated a formula the
     * Attendance model already owns.
     */
    public function productiveMinutesBetween(\Carbon\Carbon $from, \Carbon\Carbon $to): int
    {
        return (int) $this->attendances()
            ->whereNotNull('clock_out')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->sum(fn (Attendance $a) => $a->productive_minutes ?? 0);
    }

    /**
     * Performance goals assigned to this employee.
     */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    /**
     * All attendance (clock in/out) records for this employee.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Leave balance breakdown for the current year.
     * For each allowance type: allowance, used (approved days), remaining.
     * Half-day leaves count as 0.5 (via Leave::dayCount()).
     * Includes a "Carry Forward" entry from last year's remaining leaves.
     *
     * @return array<int, array<string, mixed>>
     */
    public function leaveBalances(): array
    {
        // Approved leaves this year, grouped used-days by type.
        $approved = $this->leaves()
            ->where('status', Leave::STATUS_APPROVED)
            ->whereYear('start_date', now()->year)
            ->get();

        $balances = [];
        foreach (Leave::ALLOWANCES as $type => $allowance) {
            $used = round($approved->where('leave_type', $type)->sum(fn (Leave $l) => $l->dayCount()), 1);

            $balances[] = [
                'label' => Leave::TYPES[$type],
                'allowance' => (float) $allowance,
                'used' => $used,
                'remaining' => max(0, $allowance - $used),
                'carry' => false,
            ];
        }

        // Carry-forward from previous year (display only).
        $balances[] = [
            'label' => 'Carry Forward (Previous Year)',
            'allowance' => (float) $this->carry_forward,
            'used' => 0,
            'remaining' => (float) $this->carry_forward,
            'carry' => true,
        ];

        return $balances;
    }

    /**
     * Convenience: the employee's display name, taken from the linked user.
     */
    public function getFullNameAttribute(): string
    {
        return $this->user?->name ?? '—';
    }


    /**
     * The name to show for this employee.
     *
     * Prefers the name on the employee record itself; falls back to the
     * linked login account for rows created before employees carried a name.
     */
    public function displayName(): string
    {
        return $this->name ?: ($this->user?->name ?? $this->employee_code ?? '—');
    }

    /**
     * Employees are known by the person, not the row id.
     */
    public function activityLabel(): string
    {
        return $this->displayName();
    }
}
