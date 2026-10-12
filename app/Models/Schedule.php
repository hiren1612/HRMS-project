<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    use HasFactory;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'role_tag',
        'meeting_date',
        'start_time',
        'end_time',
        'meeting_link',
        'description',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
        ];
    }

    /**
     * The user (HR/admin) who created this meeting.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Human-readable time range, e.g. "01:00 PM - 02:20 PM" (or just the start
     * time when no end time is set).
     */
    public function timeRange(): string
    {
        $start = Carbon::parse($this->start_time)->format('h:i A');

        if (! $this->end_time) {
            return $start;
        }

        return $start . ' - ' . Carbon::parse($this->end_time)->format('h:i A');
    }
}
