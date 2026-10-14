<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A performance goal assigned to an employee, tracked as a completion
 * percentage. Shown on the employee's own dashboard and managed by HR/admin.
 */
class Goal extends Model
{
    use HasFactory;
    use LogsActivity;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'title',
        'description',
        'progress',
        'due_date',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'progress' => 'integer',
        ];
    }

    /**
     * The employee this goal belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The HR/admin user who created the goal.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Limit to goals still being worked on.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * A goal at 100% is finished, whatever the form said (QA M13); dropping
     * back below 100% reopens it.
     */
    protected static function bootGoalCompletion(): void
    {
        static::saving(function (self $goal) {
            if ($goal->progress >= 100 && $goal->status === self::STATUS_ACTIVE) {
                $goal->status = self::STATUS_COMPLETED;
            } elseif ($goal->progress < 100 && $goal->status === self::STATUS_COMPLETED) {
                $goal->status = self::STATUS_ACTIVE;
            }
        });
    }

    /**
     * Still active, but its due date has already passed.
     */
    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->due_date !== null
            && $this->due_date->lt(today());
    }
}
