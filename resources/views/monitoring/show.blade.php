@section('title', $employee->name . ' — Tracking')

@php
    $hm = function (int $seconds): string {
        if ($seconds <= 0) return '—';
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $employee->name }}</h2>
                <p class="text-sm text-gray-500">
                    {{ $employee->designation ?: 'No designation' }}
                    @if ($employee->department) · {{ $employee->department }} @endif
                </p>
            </div>

            <form method="GET" class="flex items-center gap-2">
                <label for="day" class="text-sm text-gray-500">Day</label>
                <input id="day" type="date" name="day" value="{{ $day->toDateString() }}"
                       max="{{ now()->toDateString() }}"
                       onchange="this.form.requestSubmit()"
                       class="border-gray-300 rounded-md shadow-sm text-sm">
                <a href="{{ route('monitoring.index', ['day' => $day->toDateString()]) }}"
                   class="text-sm text-gray-600 hover:underline">All employees</a>
            </form>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if ($totals['tracked_seconds'] === 0)
                <div class="rounded-md bg-gray-50 border border-gray-200 p-4">
                    <p class="text-sm text-gray-700">
                        Nothing was reported for {{ $day->isoFormat('dddd, D MMMM YYYY') }}.
                        @if ($devices->isEmpty())
                            This employee has no tracking device registered at all.
                        @else
                            Their agent was installed but sent nothing — a day off, or an agent
                            that was not running.
                        @endif
                    </p>
                </div>
            @endif

            {{-- Day totals --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <x-monitoring.stat label="Tracked" :value="$hm($totals['tracked_seconds'])"
                                   :hint="$totals['sessions'].' '.Str::plural('session', $totals['sessions'])" />
                <x-monitoring.stat label="Active" :value="$hm($totals['active_seconds'])"
                                   :hint="$totals['activity_percent'].'% of tracked time'" />
                <x-monitoring.stat label="Idle" :value="$hm($totals['idle_seconds'])"
                                   :hint="$idlePeriods->count().' idle '.Str::plural('period', $idlePeriods->count())" />
                <x-monitoring.stat label="Longest idle" :value="$hm($totals['longest_idle'])"
                                   hint="single unbroken stretch" />
            </div>

            {{-- Hour-by-hour --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-5">
                <h3 class="font-semibold text-gray-800 mb-4">The day, hour by hour</h3>

                @php($peak = collect($timeline)->map(fn ($h) => $h['active'] + $h['idle'])->max() ?: 1)

                <div class="flex items-end gap-1 h-32" role="img" aria-label="Active and idle time per hour">
                    @foreach ($timeline as $hour => $slot)
                        @php($total = $slot['active'] + $slot['idle'])
                        <div class="flex-1 flex flex-col justify-end h-full"
                             title="{{ sprintf('%02d:00', $hour) }} — {{ $hm($slot['active']) }} active, {{ $hm($slot['idle']) }} idle">
                            @if ($total > 0)
                                {{-- Idle stacked above active, so the solid block
                                     at the bottom reads as work done. --}}
                                <div class="w-full bg-amber-300 rounded-t"
                                     style="height: {{ round($slot['idle'] / $peak * 100) }}%"></div>
                                <div class="w-full bg-indigo-500 {{ $slot['idle'] > 0 ? '' : 'rounded-t' }}"
                                     style="height: {{ round($slot['active'] / $peak * 100) }}%"></div>
                            @else
                                <div class="w-full bg-gray-100 rounded-t" style="height: 2%"></div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="flex gap-1 mt-2">
                    @foreach (array_keys($timeline) as $hour)
                        <div class="flex-1 text-center text-[10px] text-gray-400">
                            {{ $hour % 3 === 0 ? sprintf('%02d', $hour) : '' }}
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center gap-4 mt-4 text-xs text-gray-500">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-indigo-500"></span> Active</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-amber-300"></span> Idle</span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                {{-- Sessions --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <h3 class="font-semibold text-gray-800 mb-4">Sessions</h3>

                    @forelse ($sessions as $session)
                        <div class="py-3 {{ ! $loop->last ? 'border-b border-gray-100' : '' }}">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="text-sm font-medium text-gray-800">
                                    {{ $session->started_at?->asTime() }}
                                    —
                                    {{ $session->ended_at?->asTime() ?? 'still open' }}
                                </span>
                                <span class="text-sm text-gray-600">{{ $hm($session->trackedSeconds()) }}</span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">
                                {{ $session->device?->device_name ?? 'Unknown device' }}
                                @if ($session->ended_reason)
                                    · ended: {{ str_replace('_', ' ', $session->ended_reason) }}
                                @endif
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No sessions on this day.</p>
                    @endforelse
                </div>

                {{-- Idle periods --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <h3 class="font-semibold text-gray-800 mb-4">Idle periods</h3>

                    @forelse ($idlePeriods as $period)
                        <div class="py-2.5 flex items-baseline justify-between gap-3 {{ ! $loop->last ? 'border-b border-gray-100' : '' }}">
                            <span class="text-sm text-gray-700">
                                {{ $period->started_at?->asTime() }}
                                —
                                {{ $period->ended_at?->asTime() ?? 'ongoing' }}
                            </span>
                            <span class="text-sm text-gray-500">{{ $hm((int) $period->seconds) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No idle periods recorded.</p>
                    @endforelse

                    @if ($idlePeriods->isNotEmpty())
                        <p class="mt-4 text-xs text-gray-400">
                            Idle means no keyboard or mouse input. It covers meetings, calls and
                            reading away from the screen as readily as it covers a break.
                        </p>
                    @endif
                </div>
            </div>

            {{-- Devices --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-5">
                <h3 class="font-semibold text-gray-800 mb-4">Registered devices</h3>

                @forelse ($devices as $device)
                    <div class="py-3 flex items-baseline justify-between gap-3 flex-wrap {{ ! $loop->last ? 'border-b border-gray-100' : '' }}">
                        <div>
                            <span class="text-sm font-medium text-gray-800">{{ $device->device_name }}</span>
                            <span class="block text-xs text-gray-400">
                                {{ $device->platform }}
                                @if ($device->agent_version) · agent {{ $device->agent_version }} @endif
                            </span>
                        </div>
                        <div class="text-right">
                            @if ($device->status === 'approved')
                                <span class="text-xs font-medium text-green-700">Approved</span>
                            @elseif ($device->status === 'pending')
                                <span class="text-xs font-medium text-amber-600">Awaiting approval</span>
                            @else
                                <span class="text-xs font-medium text-gray-500">{{ ucfirst($device->status) }}</span>
                            @endif
                            <span class="block text-xs text-gray-400">
                                last seen {{ $device->last_seen_at?->diffForHumans() ?? 'never' }}
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No devices registered for this employee.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
