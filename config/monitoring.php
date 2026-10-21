<?php

/*
|--------------------------------------------------------------------------
| Employee Productivity / Time Tracking
|--------------------------------------------------------------------------
| Defaults for the desktop tracking agent and the admin monitoring screens.
|
| These are DEFAULTS only. Anything an administrator changes is stored in the
| employee_tracking_settings table and read through App\Services\Monitoring\
| TrackingSettings, which falls back to the values here. Editing this file
| changes the default for a setting nobody has touched yet; it never
| overrides a choice an administrator has already made.
|
| The whole module is inert until 'enabled' is true. Running the migrations
| does not start monitoring anybody.
*/

return [

    // Master switch. False means the agent APIs refuse work and the admin
    // screens say the module is off. Deliberately off on a fresh install.
    'enabled' => env('MONITORING_ENABLED', false),

    /*
    | Time tracking
    */
    'idle_threshold_minutes' => 5,

    // How often the agent posts a bucket of activity. Smaller means a finer
    // timeline and more requests; one minute is a reasonable balance.
    'activity_bucket_seconds' => 60,

    // A session with no contact for this long is closed by the server as
    // 'stale', so a crashed agent or a yanked power cable cannot leave a
    // session running for days.
    'session_stale_after_minutes' => 15,

    /*
    | Screenshots
    */
    'screenshots' => [
        'enabled' => true,
        'interval_minutes' => 5,

        // Never written to a public disk and never symlinked into public/.
        'disk' => 'monitoring',

        // Long edge in pixels. Captures are downscaled before upload: a
        // screenshot is there to show what someone was working on, which
        // does not need a full-resolution copy of their monitor.
        'max_dimension' => 1600,
        'jpeg_quality' => 60,
        'thumbnail_dimension' => 320,

        'max_upload_kilobytes' => 4096,

        // Deleted by the retention command after this many days.
        'retention_days' => 30,
    ],

    /*
    | Audit log. Kept far longer than the images themselves: this is the
    | record of who looked at what, which is exactly what you still want to
    | have when somebody asks months later. Not editable from the settings
    | form on purpose — shortening it should take a deployment, not a click.
    */
    'audit' => [
        'retention_days' => 365,
    ],

    /*
    | Live screen
    */
    'live' => [
        'enabled' => true,

        // The agent must confirm it has shown the on-screen indicator before
        // any frame is served. If it cannot, the session is refused.
        'require_employee_notification' => true,

        // A live session ends itself after this long whatever the admin does.
        'max_duration_minutes' => 30,
    ],

    /*
    | Working hours. Tracking outside these hours is discarded rather than
    | stored, so the module cannot quietly build a picture of someone's
    | evenings. Times are in the application timezone.
    */
    'working_hours' => [
        'enforce' => true,
        'start' => '09:00',
        'end' => '19:00',

        // 1 = Monday … 7 = Sunday, matching Carbon's ISO weekday numbering.
        'days' => [1, 2, 3, 4, 5],
    ],

    /*
    | Privacy
    */
    'privacy' => [
        // Employees must accept the monitoring policy in the HRMS before the
        // agent will track them. Without this the module is covert, which is
        // both unlawful in many places and explicitly out of scope.
        'require_acknowledgement' => true,

        // Captures are skipped entirely while one of these is the foreground
        // window. Matched case-insensitively against the window title and
        // process name reported by the agent.
        'excluded_windows' => [
            'KeePass',
            'Bitwarden',
            '1Password',
            'LastPass',
            'Keeper',
            'Authenticator',
        ],

        // Whether an employee may pause their own tracking.
        'allow_employee_pause' => true,
        'max_pause_minutes_per_day' => 60,
    ],

    /*
    | Devices
    */
    'devices' => [
        // A newly registered device is tracked only after an administrator
        // approves it. Turning this off means installing the agent is enough
        // to begin monitoring, which is not a default worth having.
        'require_approval' => true,

        'max_per_employee' => 3,
    ],

];
