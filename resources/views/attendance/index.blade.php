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

            {{-- Today's status + actions --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase">Today &mdash; {{ now()->format('l, d M Y') }}</h3>

                    {{-- Live clock (HH:MM:SS) — ticks every second in your local time --}}
                    <div x-data="{ time: '' }"
                         x-init="
                            const tick = () => time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true, timeZone: 'Asia/Kolkata' });
                            tick();
                            setInterval(tick, 1000);
                         "
                         class="flex items-center gap-2 text-2xl font-mono font-bold text-gray-800 tabular-nums">
                        <span class="inline-block h-2.5 w-2.5 rounded-full bg-green-500 animate-pulse"></span>
                        <span x-text="time">--:--:--</span>
                    </div>
                </div>

                @if ($openSession)
                    <p class="text-gray-800">
                        <span class="inline-block h-2 w-2 rounded-full bg-green-500 align-middle"></span>
                        Clocked in at <strong>{{ $openSession->clock_in->format('h:i A') }}</strong>
                        @if ($openSession->break_minutes > 0)
                            &middot; Break: <strong>{{ $openSession->break_minutes }} min</strong>
                        @endif
                    </p>

                    {{-- Live elapsed timer — counts up every second since clock-in --}}
                    <div x-data="{
                            elapsed: '',
                            start: {{ $openSession->clock_in->timestamp }} * 1000,
                            tick() {
                                const diff = Math.max(0, Math.floor((Date.now() - this.start) / 1000));
                                const h = Math.floor(diff / 3600);
                                const m = Math.floor((diff % 3600) / 60);
                                const s = diff % 60;
                                this.elapsed = h + 'h ' + String(m).padStart(2, '0') + 'm ' + String(s).padStart(2, '0') + 's';
                            }
                         }"
                         x-init="tick(); setInterval(() => tick(), 1000)"
                         class="inline-flex items-center gap-2 rounded-lg bg-indigo-50 px-4 py-2">
                        <span class="text-xs font-semibold uppercase text-indigo-500">Time elapsed</span>
                        <span class="text-2xl font-mono font-bold text-indigo-700 tabular-nums" x-text="elapsed">0h 00m 00s</span>
                    </div>

                    <div class="flex flex-wrap items-end gap-6">
                        {{-- Break time entry --}}
                        <form method="POST" action="{{ route('attendance.break') }}" class="flex items-end gap-2">
                            @csrf @method('PATCH')
                            <div>
                                <x-input-label for="break_minutes" :value="__('Break time (minutes)')" />
                                <x-text-input id="break_minutes" name="break_minutes" type="number" min="0" max="1440"
                                              class="mt-1 block w-40" :value="old('break_minutes', $openSession->break_minutes)" />
                            </div>
                            <button class="px-4 py-2 bg-gray-700 text-white text-sm rounded-md hover:bg-gray-600">Save Break</button>
                        </form>

                        {{-- Clock out --}}
                        <form method="POST" action="{{ route('attendance.clockOut') }}">
                            @csrf
                            <button class="px-5 py-2 bg-red-600 text-white text-sm font-semibold rounded-md hover:bg-red-500">End Time</button>
                        </form>
                    </div>
                    <x-input-error :messages="$errors->get('break_minutes')" class="mt-1" />
                @else
                    <p class="text-gray-600">You have not started your day yet.</p>
                    <form method="POST" action="{{ route('attendance.clockIn') }}">
                        @csrf
                        <button @disabled(! $employee)
                            class="px-5 py-2 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-500 disabled:opacity-50">
                            Start Time
                        </button>
                    </form>
                @endif
            </div>

            {{-- History --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-y-hidden whitespace-nowrap overflow-x-auto w-100">
                <table class="min-w-full divide-y divide-gray-200">
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
                                <td class="px-6 py-4">{{ $record->date->format('d M Y') }}</td>
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
