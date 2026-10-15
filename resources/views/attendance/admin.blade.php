@section('title', 'Attendance')

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Employees Attendance') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @include('attendance._clock-panel')

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <div class="px-6 py-4 border-b">
                    <h3 class="font-semibold text-gray-800">All Attendance Records</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Employee</th>
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
                                <td class="px-6 py-4 font-medium text-gray-900">{{ $record->employee->displayName() }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $record->date->format('d M Y') }}</td>
                                <td class="px-6 py-4">{{ $record->clock_in->format('h:i A') }}</td>
                                <td class="px-6 py-4">{{ $record->clock_out?->format('h:i A') ?? '—' }}</td>
                                <td class="px-6 py-4">{{ $record->isOpen() ? '—' : $record->workedLabel() }}</td>
                                <td class="px-6 py-4">{{ $record->break_minutes }} min</td>
                                <td class="px-6 py-4">
                                    @if ($record->isOpen())
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">In progress</span>
                                    @else
                                        <span class="font-semibold text-indigo-700">{{ $record->productiveLabel() }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">No attendance records yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $attendances->links() }}</div>
        </div>
    </div>
</x-app-layout>
