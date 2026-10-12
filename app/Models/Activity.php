<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One entry in the audit trail: who did what, to which record, and when.
 */
class Activity extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'url',
        'ip',
    ];

    /**
     * The account that performed the action (null if since deleted).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The record that was acted on, if any.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Newest first.
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Write an entry. Never throws: an audit-trail failure must not take down
     * the action the user was actually performing.
     */
    public static function log(string $action, string $description, ?Model $subject = null): ?self
    {
        try {
            return static::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'description' => $description,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'url' => request()->fullUrl(),
                'ip' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Tailwind tint for the icon chip, keyed off the action's verb.
     */
    public function tone(): string
    {
        return match (true) {
            str_contains($this->action, 'approved'), str_contains($this->action, 'created') => 'bg-green-100 text-green-800',
            str_contains($this->action, 'rejected'), str_contains($this->action, 'deleted') => 'bg-red-100 text-red-700',
            str_contains($this->action, 'login'), str_contains($this->action, 'logout') => 'bg-gray-100 text-gray-700',
            default => 'bg-blue-100 text-blue-700',
        };
    }
}
