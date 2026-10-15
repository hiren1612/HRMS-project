{{-- Clock-in / clock-out panel. Shared by the employee attendance page and
     the Admin/HR view, so every role can record their own day (QA N1, M7).
     Expects $employee and $openSession. --}}
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
        {{-- QA M6: a finished day used to say "you have not started yet".
             Report what actually happened, and label any further session. --}}
        @php($doneToday = $employee?->attendances()->whereDate('date', today())->whereNotNull('clock_out')->get())
        @if ($doneToday && $doneToday->isNotEmpty())
            @php($doneMins = $doneToday->sum(fn ($a) => $a->productive_minutes ?? 0))
            <p class="text-gray-800">
                <span class="inline-block h-2 w-2 rounded-full bg-gray-400 align-middle"></span>
                Day completed &mdash;
                <strong>{{ intdiv($doneMins, 60) }}h {{ str_pad((string) ($doneMins % 60), 2, '0', STR_PAD_LEFT) }}m</strong>
                productive across {{ $doneToday->count() }} {{ $doneToday->count() === 1 ? 'session' : 'sessions' }}.
            </p>
        @else
            <p class="text-gray-600">You have not started your day yet.</p>
        @endif

        <form method="POST" action="{{ route('attendance.clockIn') }}">
            @csrf
            <button @disabled(! $employee)
                class="px-5 py-2 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-500 disabled:opacity-50">
                {{ $doneToday && $doneToday->isNotEmpty() ? 'Start Another Session' : 'Start Time' }}
            </button>
        </form>
    @endif
</div>
