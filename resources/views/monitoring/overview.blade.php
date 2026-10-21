@section('title', 'Productivity Overview')

@php
    /** Seconds to "7h 32m". Kept local to the monitoring views. */
    $hm = function (int $seconds): string {
        if ($seconds <= 0) return '0m';
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Productivity Overview') }}</h2>

            <form method="GET" class="flex items-center gap-2">
                <label for="day" class="text-sm text-gray-500">Day</label>
                <input id="day" type="date" name="day" value="{{ $day->toDateString() }}"
                       max="{{ now()->toDateString() }}"
                       onchange="this.form.requestSubmit()"
                       class="border-gray-300 rounded-md shadow-sm text-sm">
                @unless ($isToday)
                    <a href="{{ route('monitoring.overview') }}" class="text-sm text-gray-600 hover:underline">Today</a>
                @endunless
            </form>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Tracking turned off company-wide is the single most useful
                 thing to say on this page, because every number below it
                 would otherwise be read as "nobody worked today". --}}
            @unless ($trackingEnabled)
                <div class="rounded-md bg-amber-50 border border-amber-200 p-4">
                    <p class="text-sm text-amber-900">
                        <span class="font-semibold">Tracking is switched off.</span>
                        Agents will not record anything until <code>tracking_enabled</code> is turned on
                        in the monitoring settings. Figures below are historical.
                    </p>
                </div>
            @endunless

            {{-- Headline figures --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <x-monitoring.stat label="Working now" :value="$workingNow->count()"
                                   :hint="$isToday ? 'signed in and reporting' : 'not applicable for a past day'" />
                <x-monitoring.stat label="Tracked today" :value="$hm($totals['tracked_seconds'])"
                                   :hint="$totals['employees'].' '.Str::plural('employee', $totals['employees']).', '.$totals['sessions'].' '.Str::plural('session', $totals['sessions'])" />
                <x-monitoring.stat label="Active time" :value="$hm($totals['active_seconds'])"
                                   :hint="$totals['activity_percent'].'% of tracked time'" />
                <x-monitoring.stat label="Idle time" :value="$hm($totals['idle_seconds'])"
                                   :hint="(100 - $totals['activity_percent']).'% of tracked time'" />
            </div>

            {{-- Things that need a person to do something --}}
            @if ($pendingDeviceCount > 0 || $staleNow->isNotEmpty() || $notReporting->isNotEmpty())
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                    @if ($pendingDeviceCount > 0)
                        <div class="bg-white shadow-sm sm:rounded-lg p-5">
                            <div class="flex items-baseline justify-between mb-3">
                                <h3 class="font-semibold text-gray-800">Devices awaiting approval</h3>
                                <span class="text-2xl font-bold text-amber-600">{{ $pendingDeviceCount }}</span>
                            </div>
                            <ul class="space-y-2 text-sm">
                                @foreach ($pendingDevices as $device)
                                    <li class="flex justify-between gap-2">
                                        <span class="text-gray-700 truncate">{{ $device->employee?->name ?? 'Unknown' }}</span>
                                        <span class="text-gray-400 truncate">{{ $device->device_name }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="mt-3 text-xs text-gray-400">
                                Approving a device needs the device screen, which is not built yet.
                            </p>
                        </div>
                    @endif

                    @if ($staleNow->isNotEmpty())
                        <div class="bg-white shadow-sm sm:rounded-lg p-5">
                            <div class="flex items-baseline justify-between mb-3">
                                <h3 class="font-semibold text-gray-800">Stopped reporting</h3>
                                <span class="text-2xl font-bold text-red-600">{{ $staleNow->count() }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mb-3">
                                Session still open but silent for over 10 minutes — a crash, a sleeping
                                laptop or a lost connection, not time at the desk.
                            </p>
                            <ul class="space-y-2 text-sm">
                                @foreach ($staleNow as $session)
                                    <li class="text-gray-700 truncate">{{ $session->employee?->name ?? 'Unknown' }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($notReporting->isNotEmpty())
                        <div class="bg-white shadow-sm sm:rounded-lg p-5">
                            <div class="flex items-baseline justify-between mb-3">
                                <h3 class="font-semibold text-gray-800">No data {{ $isToday ? 'today' : 'this day' }}</h3>
                                <span class="text-2xl font-bold text-gray-500">{{ $notReporting->count() }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mb-3">
                                Has an approved device but reported nothing. Worth a look before it is
                                read as a day not worked.
                            </p>
                            <ul class="space-y-2 text-sm">
                                @foreach ($notReporting->take(8) as $employee)
                                    <li class="text-gray-700 truncate">{{ $employee->name }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Shape of the working day --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-5">
                <h3 class="font-semibold text-gray-800 mb-4">Active time by hour</h3>

                @php($peak = max($hourly) ?: 1)

                <div class="flex items-end gap-1 h-32" role="img"
                     aria-label="Active time per hour of the day">
                    @foreach ($hourly as $hour => $seconds)
                        <div class="flex-1 flex flex-col items-center justify-end h-full">
                            <div class="w-full rounded-t {{ $seconds > 0 ? 'bg-indigo-500' : 'bg-gray-100' }}"
                                 style="height: {{ $seconds > 0 ? max(4, round($seconds / $peak * 100)) : 2 }}%"
                                 title="{{ sprintf('%02d:00', $hour) }} — {{ $hm($seconds) }} active"></div>
                        </div>
                    @endforeach
                </div>

                <div class="flex gap-1 mt-2">
                    @foreach (array_keys($hourly) as $hour)
                        <div class="flex-1 text-center text-[10px] text-gray-400">
                            {{ $hour % 3 === 0 ? sprintf('%02d', $hour) : '' }}
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Leaderboards --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <x-monitoring.ranking title="Most active" :rows="$topActive" column="active_seconds" />
                <x-monitoring.ranking title="Most idle" :rows="$mostIdle" column="idle_seconds" />
            </div>

            <p class="text-xs text-gray-400">
                Figures come from what agents reported and are not a performance rating. Idle time
                includes meetings, phone calls and reading on paper.
            </p>
        </div>
    </div>
</x-app-layout>
