@section('title', 'Attendance')

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Attendance & Productive Hours') }}</h2>
    </x-slot>

    @php
        // Tiny helper to format minutes as "Hh MMm".
        $fmt = fn (int $m) => intdiv($m, 60) . 'h ' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT) . 'm';
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            @unless ($employee)
                <div class="rounded-md bg-yellow-50 p-4 text-sm text-yellow-800">
                    You don't have an employee profile yet, so you can't record a start time. Please contact HR.
                </div>
            @endunless

            {{-- Productive-hours summary --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border-l-4 border-indigo-400">
                    <div class="text-xs text-gray-500 uppercase">Today's Productive Hours</div>
                    <div class="text-2xl font-bold text-gray-900">{{ $fmt($todayMinutes) }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border-l-4 border-blue-400">
                    <div class="text-xs text-gray-500 uppercase">This Week</div>
                    <div class="text-2xl font-bold text-gray-900">{{ $fmt($weekMinutes) }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border-l-4 border-green-400">
                    <div class="text-xs text-gray-500 uppercase">This Month</div>
                    <div class="text-2xl font-bold text-gray-900">{{ $fmt($monthMinutes) }}</div>
                </div>
            </div>

            @include('attendance._clock-panel')

            {{-- History --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="rt min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Date</th>
                            <th class="px-6 py-3">Start Time</th>
                            <th class="px-6 py-3">End Time</th>
                            <th class="px-6 py-3">Worked</th>
                            <th class="px-6 py-3">Break</th>
                            <th class="px-6 py-3">Productive</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse ($attendances as $record)
                            <tr>
                                <td data-label="Date" class="px-6 py-4">{{ $record->date->asDate() }}</td>
                                <td data-label="Start Time" class="px-6 py-4">{{ $record->clock_in->asTime() }}</td>
                                <td data-label="End Time" class="px-6 py-4">{{ $record->clock_out?->asTime() ?? '—' }}</td>
                                <td data-label="Worked" class="px-6 py-4">{{ $record->isOpen() ? '—' : $record->workedLabel() }}</td>
                                <td data-label="Break" class="px-6 py-4">{{ $record->break_minutes }} min</td>
                                <td data-label="Productive" class="px-6 py-4">
                                    @if ($record->isOpen())
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">In progress</span>
                                    @else
                                        <span class="font-semibold text-indigo-700">{{ $record->productiveLabel() }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">No attendance records yet. Record a start time to get going.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $attendances->links() }}</div>
        </div>
    </div>
</x-app-layout>
