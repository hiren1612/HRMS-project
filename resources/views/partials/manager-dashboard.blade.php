{{-- Manager overview — scoped to this manager's own reports. Expects $dash. --}}
@php($greeting = now()->hour < 12 ? 'Good Morning' : (now()->hour < 17 ? 'Good Afternoon' : 'Good Evening'))

<div class="space-y-6">

    {{-- Greeting --}}
    <div class="bg-white shadow-sm rounded-2xl p-6 flex items-center gap-4">
        @if ($dash['manager']->profilePhotoUrl())
            <img src="{{ $dash['manager']->profilePhotoUrl() }}" alt="{{ $dash['manager']->name }}"
                 class="h-14 w-14 rounded-full object-cover">
        @else
            <span class="h-14 w-14 rounded-full flex items-center justify-center text-white text-lg font-bold"
                  style="background-color:#2f80ed;">{{ $dash['manager']->initial() }}</span>
        @endif
        <div>
            <h3 class="text-lg font-bold text-gray-900">{{ $greeting }}, {{ $dash['manager']->name }}!</h3>
            <p class="text-sm text-gray-500">
                You manage {{ $dash['teamSize'] }} {{ $dash['teamSize'] === 1 ? 'person' : 'people' }}.
                @if ($dash['pendingLeaves']->isNotEmpty())
                    {{ $dash['pendingLeaves']->count() }} leave
                    {{ $dash['pendingLeaves']->count() === 1 ? 'request needs' : 'requests need' }} your decision.
                @endif
            </p>
        </div>
    </div>

    {{-- Stat strip --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4">
        @php($tiles = [
            ['My Team', $dash['teamSize'], '#eaf5fe', '#2f80ed'],
            ['Present Today', $dash['presentToday'], '#e8f8ef', '#16a34a'],
            ['On Leave', $dash['onLeaveToday'], '#fff5e6', '#d97706'],
            ['Pending Approvals', $dash['pendingLeaves']->count(), '#fdeaea', '#dc2626'],
            ['Goal Completion', $dash['goalCompletion'] === null ? '—' : $dash['goalCompletion'] . '%', '#f0edfe', '#6d5ae0'],
        ])
        @foreach ($tiles as $tile)
            <div class="bg-white shadow-sm rounded-2xl p-5">
                <div class="text-2xl font-bold" style="color: {{ $tile[3] }};">{{ $tile[1] }}</div>
                <div class="mt-1 text-xs text-gray-500 uppercase tracking-wide">{{ $tile[0] }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">

        {{-- My Actions --}}
        <div class="bg-white shadow-sm rounded-2xl p-5">
            <div class="flex items-center gap-2 mb-4">
                <span class="h-8 w-8 rounded-lg flex items-center justify-center bg-red-100 text-red-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </span>
                <h3 class="font-semibold text-gray-800">My Actions</h3>
            </div>

            <div class="space-y-1">
                <a href="{{ route('leaves.manage') }}"
                   class="flex items-center gap-3 rounded-xl px-2 py-2.5 -mx-2 hover:bg-gray-50">
                    <span class="h-9 w-9 shrink-0 rounded-xl flex items-center justify-center text-sm font-bold
                        {{ $dash['pendingLeaves']->isEmpty() ? 'bg-gray-100 text-gray-400' : 'bg-yellow-100 text-yellow-800' }}">
                        {{ $dash['pendingLeaves']->count() }}
                    </span>
                    <span class="min-w-0 flex-1 text-sm {{ $dash['pendingLeaves']->isEmpty() ? 'text-gray-400' : 'font-medium text-gray-900' }}">
                        Leave Requests to review
                    </span>
                    @if ($dash['pendingLeaves']->isNotEmpty())
                        <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    @endif
                </a>

                <div class="flex items-center gap-3 rounded-xl px-2 py-2.5 -mx-2">
                    <span class="h-9 w-9 shrink-0 rounded-xl flex items-center justify-center text-sm font-bold
                        {{ $dash['goalsAtRisk'] === 0 ? 'bg-gray-100 text-gray-400' : 'bg-red-100 text-red-700' }}">
                        {{ $dash['goalsAtRisk'] }}
                    </span>
                    <span class="min-w-0 flex-1 text-sm {{ $dash['goalsAtRisk'] === 0 ? 'text-gray-400' : 'font-medium text-gray-900' }}">
                        Goals past their due date
                    </span>
                </div>

                <div class="flex items-center gap-3 rounded-xl px-2 py-2.5 -mx-2">
                    <span class="h-9 w-9 shrink-0 rounded-xl flex items-center justify-center text-sm font-bold
                        {{ $dash['absentToday'] === 0 ? 'bg-gray-100 text-gray-400' : 'bg-blue-100 text-blue-700' }}">
                        {{ $dash['absentToday'] }}
                    </span>
                    <span class="min-w-0 flex-1 text-sm {{ $dash['absentToday'] === 0 ? 'text-gray-400' : 'font-medium text-gray-900' }}">
                        No start time today
                    </span>
                </div>
            </div>
        </div>

        {{-- Team Performance --}}
        <div class="bg-white shadow-sm rounded-2xl p-5">
            <div class="flex items-center gap-2 mb-4">
                <span class="h-8 w-8 rounded-lg flex items-center justify-center bg-blue-100 text-blue-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </span>
                <h3 class="font-semibold text-gray-800">Team Performance</h3>
            </div>

            @if ($dash['goalCompletion'] === null)
                <p class="text-sm text-gray-500">No goals assigned to your team yet.</p>
            @else
                <div class="flex items-baseline justify-between">
                    <span class="text-sm text-gray-500">Goal completion</span>
                    <span class="text-2xl font-bold text-gray-900">{{ $dash['goalCompletion'] }}%</span>
                </div>
                <div class="mt-2 h-2 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full rounded-full"
                         style="width: {{ $dash['goalCompletion'] }}%; background-color:#2f80ed;"></div>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-3">
                    <div class="rounded-xl p-3 bg-green-100">
                        <div class="text-xl font-bold text-green-800">{{ $dash['goalsOnTrack'] }}</div>
                        <div class="text-xs text-green-800">On track</div>
                    </div>
                    <div class="rounded-xl p-3 bg-red-100">
                        <div class="text-xl font-bold text-red-700">{{ $dash['goalsAtRisk'] }}</div>
                        <div class="text-xs text-red-700">At risk</div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Team Attendance today --}}
        <div class="bg-white shadow-sm rounded-2xl p-5">
            <div class="flex items-center gap-2 mb-4">
                <span class="h-8 w-8 rounded-lg flex items-center justify-center bg-green-100 text-green-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <h3 class="font-semibold text-gray-800">Team Attendance</h3>
                <span class="ml-auto text-xs text-gray-400">{{ now()->format('d M Y') }}</span>
            </div>

            @php($total = max(1, $dash['teamSize']))
            @php($rows = [
                ['Present', $dash['presentToday'], '#16a34a'],
                ['On leave', $dash['onLeaveToday'], '#d97706'],
                ['Absent', $dash['absentToday'], '#dc2626'],
            ])
            <div class="space-y-4">
                @foreach ($rows as $row)
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-700">{{ $row[0] }}</span>
                            <span class="font-semibold text-gray-900">{{ $row[1] }}</span>
                        </div>
                        <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full"
                                 style="width: {{ round($row[1] / $total * 100) }}%; background-color: {{ $row[2] }};"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- My Team --}}
    <div class="bg-white shadow-sm rounded-2xl">
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">My Team</h3>
            <span class="text-xs text-gray-400">Attendance this month &middot; average goal progress</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <th class="px-6 py-3">Employee</th>
                        <th class="px-6 py-3">Role</th>
                        <th class="px-6 py-3 w-48">Attendance</th>
                        <th class="px-6 py-3 w-48">Performance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                    @forelse ($dash['team'] as $row)
                        @php($emp = $row['employee'])
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    @if ($emp->user?->profilePhotoUrl())
                                        <img src="{{ $emp->user->profilePhotoUrl() }}" alt="{{ $emp->user->name }}" class="h-9 w-9 rounded-full object-cover">
                                    @else
                                        <span class="h-9 w-9 rounded-full flex items-center justify-center text-white text-xs font-bold"
                                              style="background-color:#2f80ed;">{{ $emp->user?->initial() ?? '?' }}</span>
                                    @endif
                                    <div>
                                        <div class="font-medium text-gray-900">{{ $emp->displayName() }}</div>
                                        <div class="text-xs text-gray-500">{{ $emp->employee_code }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">{{ $emp->designation ?: '—' }}</td>
                            <td class="px-6 py-4">
                                @if ($row['attendance'] === null)
                                    <span class="text-gray-400">—</span>
                                @else
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                            <div class="h-full rounded-full" style="width: {{ $row['attendance'] }}%; background-color:#16a34a;"></div>
                                        </div>
                                        <span class="w-10 text-right text-xs font-semibold text-gray-900">{{ $row['attendance'] }}%</span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if ($row['performance'] === null)
                                    <span class="text-xs text-gray-400">No goals set</span>
                                @else
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                            <div class="h-full rounded-full" style="width: {{ $row['performance'] }}%; background-color:#2f80ed;"></div>
                                        </div>
                                        <span class="w-10 text-right text-xs font-semibold text-gray-900">{{ $row['performance'] }}%</span>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">
                                Nobody reports to you yet. HR can set this on an employee's record using
                                <span class="font-semibold">Reports To</span>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Recent Activity --}}
    <x-recent-activity :activities="$dash['recentActivity']" title="Team Activity" />

    {{-- Announcements (full width; the calendar row sits below) --}}
    <div>
        <div class="bg-white shadow-sm rounded-2xl p-6">
            <h3 class="font-semibold text-gray-800 mb-4">Announcements</h3>
            <div class="space-y-4">
                @forelse ($dash['announcements'] as $a)
                    <div class="pl-3 border-l-2" style="border-color:#8acbf8;">
                        <div class="font-medium text-gray-900 text-sm">{{ $a->title }}</div>
                        @if ($a->body)<div class="text-xs text-gray-600">{{ $a->body }}</div>@endif
                        <div class="text-xs text-gray-400 mt-1">{{ $a->created_at->diffForHumans() }}</div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No announcements.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Calendar | Schedule & Holidays | Team Birthdays --}}
    @php($teamBirthdays = $dash['birthdays'])
    @php($thisMonthCount = $teamBirthdays->filter(fn ($b) => $b['date']->isSameMonth(now()))->count())
    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">

        {{-- Calendar --}}
        <div class="bg-white shadow-sm rounded-2xl p-5"
             x-data="hrmsCalendar({{ Js::from($dash['calendar']) }}, '{{ today()->toDateString() }}')">

            <div class="flex items-center justify-between mb-4">
                <button type="button" x-on:click="shift(-1)" aria-label="Previous month"
                        class="h-8 w-8 rounded-full flex items-center justify-center text-gray-400 hover:bg-gray-100">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button type="button" x-on:click="goToday()" title="Back to current month"
                        class="text-sm font-semibold text-gray-900 hover:opacity-70" x-text="monthLabel"></button>
                <button type="button" x-on:click="shift(1)" aria-label="Next month"
                        class="h-8 w-8 rounded-full flex items-center justify-center text-gray-400 hover:bg-gray-100">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>

            <div class="grid grid-cols-7 mb-1">
                <template x-for="(name, i) in ['S','M','T','W','T','F','S']" :key="i">
                    <div class="text-center text-xs font-medium text-gray-400 py-1" x-text="name"></div>
                </template>
            </div>

            <div class="grid grid-cols-7 gap-y-1">
                <template x-for="(cell, i) in days" :key="i">
                    <div class="flex justify-center">
                        <template x-if="cell">
                            <div class="relative h-9 w-9 rounded-full flex items-center justify-center text-sm cursor-default"
                                 :class="cell.classes" :style="cell.style" :title="cell.label">
                                <span x-text="cell.day"></span>
                                <span x-show="cell.dotColor" class="absolute bottom-1 h-1.5 w-1.5 rounded-full"
                                      :style="'background-color:' + cell.dotColor"></span>
                            </div>
                        </template>
                        <template x-if="!cell"><span class="block h-9"></span></template>
                    </div>
                </template>
            </div>

            <div class="mt-4 pt-3 border-t flex items-center justify-center gap-5 text-xs text-gray-500">
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-1.5 w-1.5 rounded-full" style="background-color:#ef4444;"></span> Holiday
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-1.5 w-1.5 rounded-full" style="background-color:#2f80ed;"></span> Meeting
                </span>
            </div>
        </div>

        {{-- Schedule & Holidays --}}
        <div class="bg-white shadow-sm rounded-2xl p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Schedule &amp; Holidays</h3>
            <div class="space-y-1">
                @forelse ($dash['agenda'] as $item)
                    <div class="flex items-center gap-3 rounded-xl px-2 py-2 -mx-2 hover:bg-gray-50">
                        <span class="h-9 w-9 shrink-0 rounded-xl flex items-center justify-center {{ $item['type'] === 'holiday' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">
                            @if ($item['type'] === 'holiday')
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            @else
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-gray-900 truncate">{{ $item['title'] }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ $item['subtitle'] }}</div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="text-xs text-gray-400 whitespace-nowrap">{{ $item['date']->format('d/m/Y') }}</div>
                            @if ($item['link'])
                                <a href="{{ $item['link'] }}" target="_blank" rel="noopener"
                                   class="text-xs font-semibold hover:underline" style="color:#2f80ed;">Join</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Nothing scheduled coming up.</p>
                @endforelse
            </div>
        </div>

        {{-- Team Birthdays --}}
        <div class="bg-white shadow-sm rounded-2xl p-5" x-data="{ scope: 'month' }">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-800">Team Birthdays</h3>
                <select x-model="scope"
                        class="text-xs rounded-lg border-gray-200 py-1 pl-2 pr-7 text-gray-600 focus:ring-0 focus:border-gray-300">
                    <option value="month">This Month</option>
                    <option value="all">All Upcoming</option>
                </select>
            </div>

            <div class="space-y-1">
                @forelse ($teamBirthdays as $b)
                    @php($bemp = $b['employee'])
                    <div class="flex items-center gap-3 rounded-xl px-2 py-2 -mx-2 hover:bg-gray-50"
                         @unless ($b['date']->isSameMonth(now())) x-show="scope === 'all'" style="display:none" @endunless>
                        @if ($bemp->user?->profilePhotoUrl())
                            <img src="{{ $bemp->user->profilePhotoUrl() }}" alt="{{ $bemp->user->name }}"
                                 class="h-10 w-10 shrink-0 rounded-xl object-cover">
                        @else
                            <span class="h-10 w-10 shrink-0 rounded-xl flex items-center justify-center text-white text-sm font-bold"
                                  style="background-color:#2f80ed;">{{ $bemp->user?->initial() ?? '?' }}</span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-gray-900 truncate">{{ $bemp->displayName() }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ $bemp->designation ?: 'Employee' }}</div>
                        </div>
                        @if ($b['daysUntil'] === 0 && $bemp->user?->email)
                            <a href="mailto:{{ $bemp->user->email }}?subject={{ rawurlencode('Happy Birthday, ' . $bemp->user->name . '!') }}"
                               class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white hover:opacity-90"
                               style="background-color:#2f80ed;">Send Wish</a>
                        @else
                            <span class="shrink-0 text-xs text-gray-400 whitespace-nowrap">
                                {{ $b['daysUntil'] === 1 ? 'Tomorrow' : $b['date']->format('d/m/Y') }}
                            </span>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No birthdays on record for your team.</p>
                @endforelse

                @if ($teamBirthdays->isNotEmpty() && $thisMonthCount === 0)
                    <p class="text-sm text-gray-500" x-show="scope === 'month'">No birthdays this month.</p>
                @endif
            </div>
        </div>
    </div>
</div>
