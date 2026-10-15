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
                <table class="rt min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Employee</th>
                            <th class="px-6 py-3">Date</th>
                            <th class="px-6 py-3">Start Time</th>
                            <th class="px-6 py-3">End Time</th>
                            <th class="px-6 py-3">Worked</th>
                            <th class="px-6 py-3">Break</th>
                            <th class="px-6 py-3">Productive</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse ($attendances as $record)
                            <tr>
                                <td data-label="Employee" class="px-6 py-4 font-medium text-gray-900">
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
                                <td data-label="Date" class="px-6 py-4 whitespace-nowrap">{{ $record->date->asDate() }}</td>
                                <td data-label="Start Time" class="px-6 py-4">{{ $record->clock_in->asTime() }}</td>
                                <td data-label="End Time" class="px-6 py-4 whitespace-nowrap">
                                    @if ($record->clock_out)
                                        {{ $record->clock_out->asTime() }}
                                        @if ($record->spansDays())
                                            {{-- QA H1: the end date was hidden, so a 97-hour row looked normal --}}
                                            <span class="block text-xs text-red-600">{{ $record->clock_out->asDate() }}</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td data-label="Worked" class="px-6 py-4">{{ $record->isOpen() ? '—' : $record->workedLabel() }}</td>
                                <td data-label="Break" class="px-6 py-4">{{ $record->break_minutes }} min</td>
                                <td data-label="Productive" class="px-6 py-4">
                                    @if ($record->isOpen())
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">In progress</span>
                                    @else
                                        <span class="font-semibold text-indigo-700">{{ $record->productiveLabel() }}</span>
                                    @endif
                                </td>

                                {{-- QA H1/M7: the correction endpoint existed but had no way in.
                                     HR can now fix a forgotten clock-out, with a reason on the record. --}}
                                <td data-label="" class="px-6 py-4 text-right whitespace-nowrap"
                                    x-data="{ fixing: false }">
                                    <button type="button" x-on:click="fixing = true"
                                            class="text-sm font-medium text-blue-600 hover:underline">Fix</button>

                                    <div x-show="fixing" x-cloak x-on:keydown.escape.window="fixing = false"
                                         class="fixed inset-0 z-50 flex items-center justify-center p-4"
                                         role="dialog" aria-modal="true"
                                         aria-label="Correct attendance for {{ $record->employee->displayName() }}">
                                        <div class="absolute inset-0 bg-gray-900/50" x-on:click="fixing = false"></div>

                                        <form method="POST" action="{{ route('attendance.correct', $record) }}"
                                              class="relative w-full max-w-md rounded-lg bg-white p-6 text-left shadow-xl">
                                            @csrf
                                            @method('PATCH')

                                            <h3 class="text-lg font-semibold text-gray-800">Correct attendance</h3>
                                            <p class="mt-1 text-sm text-gray-500">
                                                {{ $record->employee->displayName() }} &middot; {{ $record->date->asDate() }}
                                            </p>

                                            @if ($record->isSuspicious())
                                                <p class="mt-3 rounded-md bg-red-50 p-3 text-xs text-red-700">
                                                    This session is longer than
                                                    {{ \App\Models\Attendance::MAX_SESSION_MINUTES / 60 }} hours, so it is
                                                    almost certainly a forgotten clock-out. Only
                                                    {{ $record->productiveLabel() }} is being counted until it is corrected.
                                                </p>
                                            @endif

                                            <div class="mt-4 space-y-3">
                                                <div>
                                                    <x-input-label :for="'in-' . $record->id" value="Start time" required />
                                                    <x-text-input :id="'in-' . $record->id" name="clock_in" type="datetime-local"
                                                                  class="mt-1 block w-full"
                                                                  :value="$record->clock_in->format('Y-m-d\TH:i')" required />
                                                </div>
                                                <div>
                                                    <x-input-label :for="'out-' . $record->id" value="End time" />
                                                    <x-text-input :id="'out-' . $record->id" name="clock_out" type="datetime-local"
                                                                  class="mt-1 block w-full"
                                                                  :value="$record->clock_out?->format('Y-m-d\TH:i')" />
                                                    <p class="mt-1 text-xs text-gray-500">Leave blank if the session is still running.</p>
                                                </div>
                                                <div>
                                                    <x-input-label :for="'brk-' . $record->id" value="Break (minutes)" required />
                                                    <x-text-input :id="'brk-' . $record->id" name="break_minutes" type="number"
                                                                  min="0" max="1440" class="mt-1 block w-full"
                                                                  :value="$record->break_minutes" required />
                                                </div>
                                                <div>
                                                    <x-input-label :for="'why-' . $record->id" value="Reason for the change" required />
                                                    <textarea id="why-{{ $record->id }}" name="correction_note" rows="2" required
                                                              maxlength="500" placeholder="e.g. Forgot to clock out; confirmed with the employee"
                                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $record->correction_note }}</textarea>
                                                    <p class="mt-1 text-xs text-gray-500">Kept on the record so the change can be explained later.</p>
                                                </div>
                                            </div>

                                            <div class="mt-5 flex items-center justify-end gap-3">
                                                <button type="button" x-on:click="fixing = false"
                                                        class="text-sm text-gray-600 hover:underline">Cancel</button>
                                                <x-primary-button>Save correction</x-primary-button>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-500">No attendance records yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $attendances->links() }}</div>
        </div>
    </div>
</x-app-layout>
