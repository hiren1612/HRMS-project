<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'body',
        'is_published',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'expires_at' => 'date',
        ];
    }

    /**
     * QA L1: what the dashboards should show — published, and either open
     * ended or not yet past its end date.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', now()->toDateString());
            });
    }

    /**
     * Why this announcement is not on the dashboards, or null if it is.
     */
    public function hiddenReason(): ?string
    {
        if (! $this->is_published) {
            return 'Unpublished';
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return 'Ended ' . $this->expires_at->asDate();
        }

        return null;
    }

    /**
     * Keep the activity log readable rather than listing changed columns.
     *
     * @return array{0: string, 1: string}
     */
    protected function activityEntry(string $verb): array
    {
        $who = auth()->user()->name;

        return match ($verb) {
            'created' => ['announcement.created', $who . ' posted the announcement "' . $this->title . '"'],
            'updated' => ['announcement.updated', $who . ' edited the announcement "' . $this->title . '"'],
            'deleted' => ['announcement.deleted', $who . ' removed the announcement "' . $this->title . '"'],
            default => $this->defaultActivityEntry($verb),
        };
    }
}
