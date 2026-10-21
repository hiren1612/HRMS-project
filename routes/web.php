<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeProfileController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HrController;
use App\Http\Controllers\LeaveApprovalController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\Monitoring\DeviceController;
use App\Http\Controllers\Monitoring\LiveViewController;
use App\Http\Controllers\Monitoring\ScreenshotController as MonitoringScreenshotController;
use App\Http\Controllers\Monitoring\MonitoringDashboardController;
use App\Http\Controllers\Monitoring\PermissionController;
use App\Http\Controllers\Monitoring\SettingsController;
use App\Http\Controllers\Monitoring\TrackingController;
use App\Http\Middleware\EnsureMonitoringAccess;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // No public landing page — send visitors straight to login (or their dashboard).
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::get('/dashboard', [HomeController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Activity log — everyone sees their own; admin/HR see all (ActivityController scopes it).
    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Profile photo (all roles)
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto'])->name('profile.photo.destroy');

    // Self-service employee details (employee fills/edits their own HR record).
    Route::patch('/profile/employee', [EmployeeProfileController::class, 'update'])->name('profile.employee.update');

    // Leave self-service (any logged-in user): apply + view own history.
    // Fine-grained permission (must have an employee profile) is enforced by LeavePolicy.
    // Note: /leaves/create is declared before /leaves/{leave} so "create" isn't treated as an id.
    Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves.index');
    Route::get('/leaves/create', [LeaveController::class, 'create'])->name('leaves.create');
    Route::post('/leaves', [LeaveController::class, 'store'])->name('leaves.store');
    Route::get('/leaves/{leave}', [LeaveController::class, 'show'])->name('leaves.show');
    // QA M4: the applicant may withdraw their own pending request.
    Route::patch('/leaves/{leave}/cancel', [LeaveApprovalController::class, 'cancel'])->name('leaves.cancel');

    // In-app notifications (database channel).
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    // POST, not GET: opening a link must not change data (QA M11).
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');

    // Company holidays — every authenticated user can view the list.
    Route::get('/holidays', [HolidayController::class, 'index'])->name('holidays.index');

    // Attendance self-service (clock in / clock out / working hours).
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clockIn');
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clockOut');
    Route::patch('/attendance/break', [AttendanceController::class, 'break'])->name('attendance.break');

    // QA U10: time a break instead of typing the minutes in afterwards.
    Route::post('/attendance/break/start', [AttendanceController::class, 'startBreak'])->name('attendance.break.start');
    Route::post('/attendance/break/end', [AttendanceController::class, 'endBreak'])->name('attendance.break.end');

    // QA M7 / H1: organisation-wide export and HR correction of a session.
    Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
    Route::patch('/attendance/{attendance}/correct', [AttendanceController::class, 'correct'])->name('attendance.correct');
});

// Leave approval area — only managers, HR and admins.
Route::middleware(['auth', 'role:admin,hr,manager'])->group(function () {
    Route::get('/leave-requests', [LeaveApprovalController::class, 'index'])->name('leaves.manage');
    Route::patch('/leave-requests/{leave}/approve', [LeaveApprovalController::class, 'approve'])->name('leaves.approve');
    Route::patch('/leave-requests/{leave}/reject', [LeaveApprovalController::class, 'reject'])->name('leaves.reject');
});

/*
|--------------------------------------------------------------------------
| Role-protected routes
|--------------------------------------------------------------------------
| 'auth' runs first (user must be logged in), then 'role' checks the role.
| Anyone logged in but lacking the role gets a 403 Forbidden response.
*/

// Admin-only area: user & role management.
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::patch('/admin/users/{user}/role', [AdminController::class, 'updateRole'])->name('admin.users.role');
    Route::post('/admin/timecamp/sync', [AdminController::class, 'syncTimeCamp'])->name('admin.timecamp.sync');
});

// HR area — accessible to BOTH admins and HR staff (comma = "any of these").
Route::middleware(['auth', 'role:admin,hr'])->group(function () {
    Route::get('/hr', [HrController::class, 'index'])->name('hr.dashboard');

    // Employee management — full CRUD (index/create/store/show/edit/update/destroy).
    Route::resource('employees', EmployeeController::class);

    // QA L2: announcements appear on every dashboard, so HR needs a screen to
    // post, edit, unpublish and remove them.
    Route::resource('announcements', AnnouncementController::class)->except(['show']);

    // Bootstrap HRMS dashboard with summary statistics.
    Route::get('/hrms-dashboard', [DashboardController::class, 'index'])->name('hrms.dashboard');

    // Holiday management (create/store/edit/update/destroy) — HR/admin only.
    Route::resource('holidays', HolidayController::class)->except(['index', 'show']);

    // Import public holidays / festivals from Google's public calendar.
    Route::post('/holidays/import-google', [HolidayController::class, 'importGoogle'])->name('holidays.importGoogle');

    // Meeting schedules — HR/admin manage; shown on the dashboard "Schedules" card.
    Route::resource('schedules', ScheduleController::class)->except(['show']);

    // Employee performance goals — HR/admin assign them; employees see
    // their own on the dashboard.
    Route::resource('goals', GoalController::class)->except(['show']);
});

/*
|--------------------------------------------------------------------------
| Productivity monitoring
|--------------------------------------------------------------------------
|
| Gated by EnsureMonitoringAccess rather than by role. Being an administrator
| of the HRMS is not the same as being cleared to see what a colleague had on
| screen, so access comes from a row in employee_monitoring_permissions and
| nobody has it by default.
|
| These three screens are read-only. Screenshots, live viewing and the
| settings form are separate capabilities and arrive with their own screens.
|
*/
Route::middleware(['auth', EnsureMonitoringAccess::class])->prefix('monitoring')->name('monitoring.')->group(function () {
    Route::get('/', [MonitoringDashboardController::class, 'index'])->name('overview');
    Route::get('/tracking', [TrackingController::class, 'index'])->name('index');
    Route::get('/tracking/{employee}', [TrackingController::class, 'show'])->name('show');

    // Changing what the module records is a separate clearance from reading
    // what it recorded.
    Route::middleware(EnsureMonitoringAccess::class . ':settings')->group(function () {
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
        Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });

    // Looking at a photograph of somebody's screen is its own clearance.
    Route::middleware(EnsureMonitoringAccess::class . ':screenshots')->group(function () {
        Route::get('/screenshots', [MonitoringScreenshotController::class, 'index'])->name('screenshots');
        // The only route that serves the image bytes. They sit on a private
        // disk with no public URL precisely so this check cannot be skipped.
        Route::get('/screenshots/{screenshot}', [MonitoringScreenshotController::class, 'show'])->name('screenshots.show');
        Route::delete('/screenshots/{screenshot}', [MonitoringScreenshotController::class, 'destroy'])->name('screenshots.destroy');
    });

    // Watching somebody in real time is the most intrusive thing the module
    // can do, and has its own capability again.
    Route::middleware(EnsureMonitoringAccess::class . ':live')->group(function () {
        Route::get('/live', [LiveViewController::class, 'index'])->name('live');
        Route::post('/live', [LiveViewController::class, 'store'])->name('live.store');
        Route::get('/live/{session}/frame', [LiveViewController::class, 'frame'])->name('live.frame');
        Route::delete('/live/{session}', [LiveViewController::class, 'destroy'])->name('live.destroy');
    });

    // Approving a device is what lets an agent start reporting at all.
    Route::middleware(EnsureMonitoringAccess::class . ':devices')->group(function () {
        Route::get('/devices', [DeviceController::class, 'index'])->name('devices');
        Route::patch('/devices/{device}/approve', [DeviceController::class, 'approve'])->name('devices.approve');
        Route::patch('/devices/{device}/revoke', [DeviceController::class, 'revoke'])->name('devices.revoke');
    });
});

/*
| Deciding who may watch is not itself a monitoring capability. It is gated
| on the administrator role instead, so somebody cleared to review
| screenshots cannot use that clearance to clear anyone else.
*/
Route::middleware(['auth', 'role:admin'])->prefix('monitoring')->name('monitoring.')->group(function () {
    Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions');
    Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store');
    Route::patch('/permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');
});

require __DIR__.'/auth.php';
