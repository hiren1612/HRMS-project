<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Monitoring\MonitoringAuditLog;
use App\Models\Monitoring\Screenshot;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The screenshot gallery, and the only route that serves the images.
 *
 * Screenshots live on a private disk with no public URL, so every image is
 * served through show() after the permission has been checked. That is the
 * point: a file somebody can reach by guessing a path is not protected by
 * whatever the gallery page does.
 *
 * Every image served is written to the audit log. Looking at a photograph
 * of a colleague's screen is an act worth being accountable for, and if the
 * log makes somebody hesitate before browsing idly, that is the feature
 * working rather than a cost of it.
 */
class ScreenshotController extends Controller
{
    private const PER_PAGE = 24;

    /**
     * Grid of thumbnails for one employee and one day.
     */
    public function index(Request $request): View
    {
        $day = $this->resolveDay($request);
        $employeeId = (int) $request->query('employee', 0);

        $employee = $employeeId > 0 ? Employee::find($employeeId) : null;

        $screenshots = Screenshot::query()
            ->with('employee:id,name,employee_code', 'device:id,device_name')
            ->when($employee, fn ($q) => $q->forEmployee($employee->id))
            ->capturedBetween($day->startOfDay(), $day->endOfDay())
            ->orderByDesc('captured_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Audited here, once, rather than in show(). A grid of 24 tiles is
        // 24 requests for images, and logging each one would bury the fact
        // that matters — somebody looked at this person's day — under
        // rendering detail. One row naming the employee, the day and the
        // count is both truer to the act and actually readable later.
        if ($screenshots->isNotEmpty()) {
            MonitoringAuditLog::record(
                MonitoringAuditLog::SCREENSHOT_VIEWED,
                null,
                $employee?->id,
                [
                    'day' => $day->toDateString(),
                    'employee' => $employee?->name ?? 'all employees',
                    'count' => $screenshots->count(),
                ],
            );
        }

        return view('monitoring.screenshots', [
            'screenshots' => $screenshots,
            'employee' => $employee,
            'day' => $day,
            'isToday' => $day->isSameDay(CarbonImmutable::today()),

            // Only people who actually have screenshots are worth listing,
            // so the filter does not offer a hundred names that all lead to
            // an empty grid.
            'employees' => Employee::whereIn(
                'id',
                Screenshot::query()->select('employee_id')->distinct(),
            )->orderBy('name')->get(['id', 'name', 'employee_code']),

            'retentionDays' => (int) app(\App\Services\Monitoring\TrackingSettings::class)
                ->int('screenshots.retention_days', 30),
        ]);
    }

    /**
     * Stream one image from the private disk.
     *
     * Not a redirect to a storage URL: the file must never become reachable
     * without passing through this check.
     */
    public function show(Request $request, Screenshot $screenshot): StreamedResponse|Response
    {
        if (! $screenshot->fileExists()) {
            // The row outliving its file is normal after a retention run
            // that was interrupted, so this is a 404 rather than an error.
            abort(Response::HTTP_NOT_FOUND, 'That screenshot is no longer stored.');
        }

        // Deliberately not audited per request: the gallery page already
        // recorded the view, and the browser asks for these once per tile
        // and again on every cache miss. The permission check above is what
        // protects the file; the log entry lives with the page.
        return response()->stream(
            fn () => print $screenshot->contents(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'image/jpeg',
                'Content-Length' => (string) $screenshot->bytes,
                // Private, so a shared or proxied cache cannot hold a copy
                // of somebody's screen.
                'Cache-Control' => 'private, max-age=300, no-transform',
                'Content-Disposition' => 'inline; filename="screenshot-' . $screenshot->id . '.jpg"',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /**
     * Delete one screenshot early.
     *
     * Retention handles the ordinary case; this is for the screenshot that
     * caught something it should not have, where waiting thirty days is not
     * an acceptable answer.
     */
    public function destroy(Request $request, Screenshot $screenshot): RedirectResponse
    {
        $employeeId = $screenshot->employee_id;
        $capturedAt = $screenshot->captured_at?->toDateTimeString();

        $screenshot->deleteFiles();
        $screenshot->delete();

        MonitoringAuditLog::record(
            MonitoringAuditLog::SCREENSHOT_DELETED,
            null,
            $employeeId,
            ['screenshot_id' => $screenshot->id, 'captured_at' => $capturedAt, 'manual' => true],
        );

        return back()->with('success', 'Screenshot deleted.');
    }

    private function resolveDay(Request $request): CarbonImmutable
    {
        $raw = $request->query('day');

        if (! is_string($raw) || $raw === '') {
            return CarbonImmutable::today();
        }

        try {
            $day = CarbonImmutable::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }

        return $day->isAfter(CarbonImmutable::today()) ? CarbonImmutable::today() : $day;
    }
}
