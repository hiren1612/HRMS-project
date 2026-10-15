<?php

use App\Http\Controllers\AdminController;
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

require __DIR__.'/auth.php';
