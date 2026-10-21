<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\Monitoring\MonitoringAuditLog;
use App\Models\Monitoring\MonitoringPermission;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Who is cleared to see monitoring data.
 *
 * Gated on the administrator role rather than on a monitoring capability,
 * and that is deliberate: using monitoring and deciding who may use it are
 * different acts. Somebody cleared to review screenshots should not thereby
 * be able to clear anybody else.
 *
 * Granting to yourself is allowed. An administrator can already write this
 * row directly in the database, so blocking it would be theatre — and
 * somebody has to be able to create the first row. Every grant is recorded
 * with who made it, so a self-grant is visible rather than prevented.
 */
class PermissionController extends Controller
{
    /** Form field => the column it sets. */
    private const CAPABILITIES = [
        'can_view_screenshots' => 'See screenshots',
        'can_view_live_screen' => 'Watch a live screen',
        'can_manage_devices' => 'Approve and revoke devices',
        'can_manage_settings' => 'Change monitoring settings',
    ];

    public function index(): View
    {
        return view('monitoring.permissions', [
            'permissions' => MonitoringPermission::with('user:id,name,email,role', 'grantedBy:id,name')
                ->get()
                ->sortBy(fn ($p) => $p->user?->name ?? ''),

            // Only staff who could plausibly need it. Offering the whole
            // company in one select makes a mis-click more likely than a
            // correct choice.
            // A subquery rather than whereDoesntHave, so User does not need
            // a relation added to it for one screen in one module.
            'candidates' => User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_HR, User::ROLE_MANAGER])
                ->whereNotIn('id', MonitoringPermission::query()->select('user_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role']),

            'capabilities' => self::CAPABILITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'can_view_screenshots' => ['nullable', 'boolean'],
            'can_view_live_screen' => ['nullable', 'boolean'],
            'can_manage_devices' => ['nullable', 'boolean'],
            'can_manage_settings' => ['nullable', 'boolean'],
        ]);

        if (MonitoringPermission::where('user_id', $data['user_id'])->exists()) {
            return back()->withErrors(['user_id' => 'That person already has monitoring access.']);
        }

        $permission = MonitoringPermission::create([
            'user_id' => $data['user_id'],
            'can_view_screenshots' => $request->boolean('can_view_screenshots'),
            'can_view_live_screen' => $request->boolean('can_view_live_screen'),
            'can_manage_devices' => $request->boolean('can_manage_devices'),
            'can_manage_settings' => $request->boolean('can_manage_settings'),
            'granted_by' => $request->user()->id,
        ]);

        $this->audit('granted', $permission, $request);

        return back()->with('success', 'Monitoring access granted to ' . $permission->user->name . '.');
    }

    public function update(Request $request, MonitoringPermission $permission): RedirectResponse
    {
        $request->validate([
            'can_view_screenshots' => ['nullable', 'boolean'],
            'can_view_live_screen' => ['nullable', 'boolean'],
            'can_manage_devices' => ['nullable', 'boolean'],
            'can_manage_settings' => ['nullable', 'boolean'],
        ]);

        $before = $permission->only(array_keys(self::CAPABILITIES));

        $permission->update([
            'can_view_screenshots' => $request->boolean('can_view_screenshots'),
            'can_view_live_screen' => $request->boolean('can_view_live_screen'),
            'can_manage_devices' => $request->boolean('can_manage_devices'),
            'can_manage_settings' => $request->boolean('can_manage_settings'),
            'granted_by' => $request->user()->id,
        ]);

        $this->audit('changed', $permission, $request, $before);

        return back()->with('success', 'Updated access for ' . $permission->user->name . '.');
    }

    public function destroy(Request $request, MonitoringPermission $permission): RedirectResponse
    {
        $name = $permission->user?->name ?? 'that user';

        $this->audit('revoked', $permission, $request);

        $permission->delete();

        return back()->with('success', "Monitoring access removed from {$name}.");
    }

    /**
     * @param  array<string, mixed>|null  $before
     */
    private function audit(string $verb, MonitoringPermission $permission, Request $request, ?array $before = null): void
    {
        MonitoringAuditLog::record(
            MonitoringAuditLog::PERMISSION_CHANGED,
            $permission,
            null,
            array_filter([
                'verb' => $verb,
                'subject_user_id' => $permission->user_id,
                'subject_user' => $permission->user?->name,
                // A grant somebody made to themselves is the one worth being
                // able to find later.
                'self_grant' => $permission->user_id === $request->user()->id,
                'from' => $before,
                'to' => $permission->only(array_keys(self::CAPABILITIES)),
            ], fn ($v) => $v !== null),
        );
    }
}
