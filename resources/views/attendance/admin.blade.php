@section('title', 'Attendance')

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Employees Attendance') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @include('attendance._clock-panel')

            {{-- QA M7: filter by person and date, flag problem rows, export. --}}
            <form method="GET" class="bg-white shadow-sm sm:rounded-lg p-4 mb-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label for="employee" class="block text-xs font-medium text-gray-600 mb-1">Employee</label>
                        <select id="employee" name="employee" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">Everyone</option>
                            @foreach ($employees as $id => $name)
                                <option value="{{ $id }}" @selected((string) request('employee') === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="from" class="block text-xs font-medium text-gray-600 mb-1">From</label>
                        <x-text-input id="from" name="from" type="date" class="block w-full" :value="request('from')" />
                    </div>
                    <div>
                        <label for="to" class="block text-xs font-medium text-gray-600 mb-1">To</label>
                        <x-text-input id="to" name="to" type="date" class="block w-full" :value="request('to')" />
                    </div>
                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="flagged" value="1" @checked(request()->boolean('flagged'))
                                   class="rounded border-gray-300">
                            Needs attention only
                        </label>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <x-primary-button>Filter</x-primary-button>
                    @if (request()->hasAny(['employee', 'from', 'to', 'flagged']))
                        <a href="{{ route('attendance.index') }}" class="text-sm text-gray-600 hover:underline">Clear</a>
                    @endif
                    <a href="{{ route('attendance.export', request()->query()) }}"
                       class="text-sm font-medium hover:underline" style="color:#2f80ed;">Export CSV</a>
                    <span class="ml-auto text-sm text-gray-500">{{ $attendances->total() }} records</span>
                </div>
            </form>

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
                                <td class="px-6 py-4 font-medium text-gray-900">
                                    {{ $record->employee->displayName() }}
                                    @if ($record->isSuspicious())
                                        <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700"
                                              title="Longer than {{ \App\Models\Attendance::MAX_SESSION_MINUTES / 60 }} hours - likely a forgotten clock-out">Check</span>
                                    @elseif ($record->closed_by === \App\Models\Attendance::CLOSED_AUTO)
                                        <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">Auto-closed</span>
                                    @elseif ($record->closed_by === \App\Models\Attendance::CLOSED_HR)
                                        <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">Corrected</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $record->date->format('d M Y') }}</td>
                                <td class="px-6 py-4">{{ $record->clock_in->format('h:i A') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($record->clock_out)
                                        {{ $record->clock_out->format('h:i A') }}
                                        @if ($record->spansDays())
                                            {{-- QA H1: the end date was hidden, so a 97-hour row looked normal --}}
                                            <span class="block text-xs text-red-600">{{ $record->clock_out->format('d M Y') }}</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
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
