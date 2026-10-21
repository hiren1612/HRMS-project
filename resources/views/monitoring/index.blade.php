@section('title', 'Tracking')

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
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Tracking') }}</h2>
            <a href="{{ route('monitoring.overview', ['day' => $day->toDateString()]) }}"
               class="text-sm text-indigo-600 hover:underline">Overview →</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Filters --}}
            <form method="GET" class="bg-white shadow-sm sm:rounded-lg p-4 mb-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="lg:col-span-2">
                        <label for="track-search" class="sr-only">Search employees</label>
                        <x-text-input id="track-search" name="search" type="search" class="block w-full"
                                      :value="request('search')"
                                      placeholder="Search name, code or department" />
                    </div>

                    <label for="track-dept" class="sr-only">Filter by department</label>
                    <select id="track-dept" name="department" aria-label="Filter by department"
                            class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department }}" @selected(request('department') === $department)>{{ $department }}</option>
                        @endforeach
                    </select>

                    <div>
                        <label for="track-day" class="sr-only">Day</label>
                        <input id="track-day" type="date" name="day" value="{{ $day->toDateString() }}"
                               max="{{ now()->toDateString() }}"
                               class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                    </div>
                </div>

                <div class="mt-3 flex items-center gap-3 flex-wrap">
                    <x-primary-button>Apply</x-primary-button>
                    @if (request()->hasAny(['search', 'department', 'day']))
                        <a href="{{ route('monitoring.index') }}" class="text-sm text-gray-600 hover:underline">Clear</a>
                    @endif
                    <span class="ml-auto text-sm text-gray-500">
                        {{ $rows->total() }} {{ Str::plural('employee', $rows->total()) }} ·
                        {{ $hm($totalTracked) }} tracked
                    </span>
                </div>
            </form>

            {{-- Table. Wrapped for narrow screens rather than letting the page
                 scroll sideways as a whole. --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="rt min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-600">Employee</th>
                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-600">Department</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold text-gray-600">Tracked</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold text-gray-600">Active</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold text-gray-600">Idle</th>
                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-600">Activity</th>
                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($rows as $row)
                                <tr class="hover:bg-gray-50">
                                    <td data-label="Employee" class="px-4 py-3">
                                        <a href="{{ route('monitoring.show', ['employee' => $row['employee'], 'day' => $day->toDateString()]) }}"
                                           class="font-medium text-gray-800 hover:underline">
                                            {{ $row['employee']->name }}
                                        </a>
                                        @if ($row['employee']->employee_code)
                                            <span class="block text-xs text-gray-400">{{ $row['employee']->employee_code }}</span>
                                        @endif
                                    </td>
                                    <td data-label="Department" class="px-4 py-3 text-gray-600">{{ $row['employee']->department ?: '—' }}</td>
                                    <td data-label="Tracked" class="px-4 py-3 text-right text-gray-800">{{ $hm($row['tracked_seconds']) }}</td>
                                    <td data-label="Active" class="px-4 py-3 text-right text-gray-800">{{ $hm($row['active_seconds']) }}</td>
                                    <td data-label="Idle" class="px-4 py-3 text-right text-gray-500">{{ $hm($row['idle_seconds']) }}</td>
                                    <td data-label="Activity" class="px-4 py-3">
                                        @if ($row['tracked_seconds'] > 0)
                                            <div class="flex items-center gap-2">
                                                <div class="w-20 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                                    <div class="h-full bg-indigo-500 rounded-full"
                                                         style="width: {{ $row['activity_percent'] }}%"></div>
                                                </div>
                                                <span class="text-xs text-gray-500">{{ $row['activity_percent'] }}%</span>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td data-label="Status" class="px-4 py-3">
                                        @if ($row['running'])
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-green-700">
                                                <span class="h-2 w-2 rounded-full bg-green-500"></span> Running
                                            </span>
                                        @elseif ($row['tracked_seconds'] > 0)
                                            <span class="text-xs text-gray-500">Finished</span>
                                        @else
                                            <span class="text-xs text-gray-400">No data</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                                        No employees match these filters.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($rows->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100">
                        {{ $rows->links() }}
                    </div>
                @endif
            </div>

            <p class="mt-4 text-xs text-gray-400">
                "No data" means the agent reported nothing for this day. That can be a day off,
                an agent that was never installed, or one that stopped working — the three look
                identical here.
            </p>
        </div>
    </div>
</x-app-layout>
