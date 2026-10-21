<?php

namespace App\Models\Monitoring;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One administrator-changed setting.
 *
 * Read through App\Services\Monitoring\TrackingSettings rather than
 * directly, so config defaults and database overrides resolve in one place.
 */
class TrackingSetting extends Model
{
    protected $table = 'employee_tracking_settings';

    /** @var list<string> */
    protected $fillable = [
        'key',
        'value',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
