<?php

namespace App\Models\Monitoring;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who is cleared to see monitoring data.
 *
 * Being an administrator of the HRMS is not the same as being cleared to
 * look at a colleague's screen, and the existing four roles cannot express
 * the difference. Absence of a row means no access.
 */
class MonitoringPermission extends Model
{
    protected $table = 'employee_monitoring_permissions';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'can_view_screenshots',
        'can_view_live_screen',
        'can_manage_devices',
        'can_manage_settings',
        'granted_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'can_view_screenshots' => 'boolean',
            'can_view_live_screen' => 'boolean',
            'can_manage_devices' => 'boolean',
            'can_manage_settings' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
