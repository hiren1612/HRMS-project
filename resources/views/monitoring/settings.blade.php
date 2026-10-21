@section('title', 'Monitoring Settings')

@php
    // Dotted keys become underscored field names, because Laravel would read
    // "screenshots.interval_minutes" as nested array input.
    $field = fn (string $key) => str_replace('.', '_', $key);
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Monitoring Settings') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('monitoring.settings.update') }}" class="space-y-6">
                @csrf
                @method('PATCH')

                {{-- Master switch, on its own, because it governs everything
                     below it and burying it in a list invites it being
                     flipped by accident. --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="enabled" value="1" @checked($values['enabled'])
                               class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm">
                        <span>
                            <span class="block font-semibold text-gray-800">Tracking enabled</span>
                            <span class="block text-sm text-gray-500 mt-0.5">
                                The master switch. With this off, agents record nothing at all —
                                no activity, no screenshots, no live viewing — whatever the
                                individual settings below say.
                            </span>
                        </span>
                    </label>
                </div>

                {{-- Activity --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Activity</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <x-monitoring.setting-number
                            name="idle_threshold_minutes" label="Idle after"
                            :value="$values['idle_threshold_minutes']" suffix="minutes"
                            hint="No keyboard or mouse for this long counts as idle. The whole stretch is back-dated, not just the time after the threshold." />

                        <x-monitoring.setting-number
                            name="session_stale_after_minutes" label="Treat a silent session as stale after"
                            :value="$values['session_stale_after_minutes']" suffix="minutes"
                            hint="A session still open but not reporting. Shown separately on the overview rather than counted as somebody at their desk." />
                    </div>
                </div>

                {{-- Screenshots --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Screenshots</h3>

                    <label class="flex items-start gap-3 mb-5">
                        <input type="checkbox" name="screenshots_enabled" value="1" @checked($values['screenshots.enabled'])
                               class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm">
                        <span>
                            <span class="block font-medium text-gray-800">Capture screenshots</span>
                            <span class="block text-sm text-gray-500 mt-0.5">
                                Enforced on the server, so turning this off stops uploads even from
                                an agent that has not noticed yet.
                            </span>
                        </span>
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <x-monitoring.setting-number
                            name="screenshots_interval_minutes" label="Capture every"
                            :value="$values['screenshots.interval_minutes']" suffix="minutes" />

                        <x-monitoring.setting-number
                            name="screenshots_retention_days" label="Delete after"
                            :value="$values['screenshots.retention_days']" suffix="days"
                            hint="Images and their database rows are removed together. Shorter is kinder and cheaper." />
                    </div>
                </div>

                {{-- Live screen --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Live screen</h3>

                    <label class="flex items-start gap-3 mb-5">
                        <input type="checkbox" name="live_enabled" value="1" @checked($values['live.enabled'])
                               class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm">
                        <span>
                            <span class="block font-medium text-gray-800">Allow live viewing</span>
                            <span class="block text-sm text-gray-500 mt-0.5">
                                An on-screen indicator always appears on the employee's machine first,
                                and the server refuses every frame until the agent confirms it is
                                showing. That cannot be switched off here.
                            </span>
                        </span>
                    </label>

                    <div class="sm:w-1/2">
                        <x-monitoring.setting-number
                            name="live_max_duration_minutes" label="End a live session after"
                            :value="$values['live.max_duration_minutes']" suffix="minutes"
                            hint="A hard ceiling, whatever the viewer does." />
                    </div>
                </div>

                {{-- Working hours --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Working hours</h3>

                    <label class="flex items-start gap-3 mb-5">
                        <input type="checkbox" name="working_hours_enforce" value="1" @checked($values['working_hours.enforce'])
                               class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm">
                        <span>
                            <span class="block font-medium text-gray-800">Only record inside working hours</span>
                            <span class="block text-sm text-gray-500 mt-0.5">
                                Activity outside these hours is discarded rather than stored, so the
                                module cannot quietly build a picture of somebody's evenings.
                            </span>
                        </span>
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="working_hours_start" value="Day starts" />
                            <input id="working_hours_start" type="time" name="working_hours_start"
                                   value="{{ old('working_hours_start', $values['working_hours.start']) }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                        </div>
                        <div>
                            <x-input-label for="working_hours_end" value="Day ends" />
                            <input id="working_hours_end" type="time" name="working_hours_end"
                                   value="{{ old('working_hours_end', $values['working_hours.end']) }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                        </div>
                    </div>
                </div>

                {{-- Privacy --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Privacy</h3>

                    <label class="flex items-start gap-3 mb-5">
                        <input type="checkbox" name="privacy_allow_employee_pause" value="1" @checked($values['privacy.allow_employee_pause'])
                               class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm">
                        <span>
                            <span class="block font-medium text-gray-800">Let employees pause tracking</span>
                            <span class="block text-sm text-gray-500 mt-0.5">
                                A pause is recorded as a pause, not hidden. Removing this makes the
                                agent something done to people rather than with them.
                            </span>
                        </span>
                    </label>

                    <div class="sm:w-1/2">
                        <x-monitoring.setting-number
                            name="privacy_max_pause_minutes_per_day" label="Most pause time per day"
                            :value="$values['privacy.max_pause_minutes_per_day']" suffix="minutes"
                            hint="0 means no limit." />
                    </div>
                </div>

                {{-- Devices --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Devices</h3>

                    <label class="flex items-start gap-3 mb-5">
                        <input type="checkbox" name="devices_require_approval" value="1" @checked($values['devices.require_approval'])
                               class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm">
                        <span>
                            <span class="block font-medium text-gray-800">New devices need approving</span>
                            <span class="block text-sm text-gray-500 mt-0.5">
                                With this off, any machine someone signs into starts reporting
                                immediately. Useful during a rollout; a poor steady state.
                            </span>
                        </span>
                    </label>

                    <div class="sm:w-1/2">
                        <x-monitoring.setting-number
                            name="devices_max_per_employee" label="Devices per employee"
                            :value="$values['devices.max_per_employee']" suffix="devices" />
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>Save settings</x-primary-button>
                    <a href="{{ route('monitoring.overview') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                </div>
            </form>

            <p class="mt-6 text-xs text-gray-400">
                Every change is written to the monitoring audit log with who made it and what it
                was before.
            </p>
        </div>
    </div>
</x-app-layout>
