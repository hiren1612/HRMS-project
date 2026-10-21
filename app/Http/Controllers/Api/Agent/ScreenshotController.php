<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Monitoring\Screenshot;
use App\Models\Monitoring\TrackingSession;
use App\Services\Monitoring\TrackingSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Screenshot upload.
 *
 * Files go to the private "monitoring" disk, never to public storage. There
 * is no URL that serves them; phase 6 adds an authorised controller that
 * streams them to somebody who has been granted the screenshots capability.
 */
class ScreenshotController extends Controller
{
    public function __construct(private readonly TrackingSettings $settings)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['nullable', 'integer'],
            'captured_at' => ['required', 'date'],
            'status_at_capture' => ['nullable', 'string', 'max:20'],
            'width' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'height' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'screenshot' => ['required', 'file', 'mimetypes:image/jpeg', 'max:8192'],
        ]);

        $device = $request->attributes->get('tracking_device');
        $employee = $request->attributes->get('tracking_employee');

        // The agent checks this too, but the agent runs on a machine the
        // employee controls. A server that trusts the client's word about
        // whether screenshots are allowed is not enforcing anything.
        if (! $this->settings->screenshotsEnabled()) {
            return response()->json([
                'message' => 'Screenshots are disabled.',
                'action' => 'stop_screenshots',
            ], 403);
        }

        $capturedAt = Carbon::parse($data['captured_at']);

        if (! $this->settings->withinWorkingHours($capturedAt)) {
            // Acknowledged so the agent stops retrying, but not stored.
            return response()->json(['skipped' => 'outside_working_hours'], 202);
        }

        $session = null;

        if (! empty($data['session_id'])) {
            $session = TrackingSession::where('id', $data['session_id'])
                ->where('device_id', $device->id)
                ->first();
        }

        // The table is unique on (device_id, captured_at) so a retried
        // upload cannot create a second copy. Check first rather than
        // catching the constraint violation, so the duplicate file is never
        // written to disk at all.
        $existing = Screenshot::where('device_id', $device->id)
            ->where('captured_at', $capturedAt)
            ->first();

        if ($existing) {
            return response()->json(['id' => $existing->id, 'duplicate' => true], 200);
        }

        $file = $request->file('screenshot');
        $contents = file_get_contents($file->getRealPath());

        // Foldered by employee and day: retention deletes a day's folder
        // rather than hunting individual files, and nothing is guessable
        // from the path.
        $path = sprintf(
            'screenshots/%d/%s/%s.jpg',
            $employee->id,
            $capturedAt->format('Y-m-d'),
            Str::uuid(),
        );

        Storage::disk('monitoring')->put($path, $contents);

        $screenshot = Screenshot::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'session_id' => $session?->id,
            'captured_at' => $capturedAt,
            'disk' => 'monitoring',
            'path' => $path,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'bytes' => strlen($contents),
            'status_at_capture' => $data['status_at_capture'] ?? 'active',
            'checksum' => hash('sha256', $contents),
        ]);

        return response()->json(['id' => $screenshot->id], 201);
    }
}
