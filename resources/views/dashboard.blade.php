<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Generic welcome (only when there's no admin/employee rich dashboard) --}}
            @if (empty($adminDash) && empty($empDash) && empty($mgrDash))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <p class="text-lg">
                            {{ __("Welcome back") }}, <span class="font-semibold">{{ Auth::user()->name }}</span>!
                        </p>
                        <p class="mt-2 text-sm text-gray-600">
                            Your role:
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold uppercase
                                {{ Auth::user()->isAdmin() ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ Auth::user()->role }}
                            </span>
                        </p>
                    </div>
                </div>
            @endif

            @if (!empty($mgrDash))
                @include('partials.manager-dashboard', ['dash' => $mgrDash])
            @endif

            @if (!empty($adminDash))
                @include('partials.org-dashboard', ['dash' => $adminDash])
            @elseif (empty($mgrDash))

            {{-- ===================== EMPLOYEE DASHBOARD ===================== --}}
            @if (!empty($empDash))
                @php($emp = $empDash['employee'])

                {{-- 1. Welcome card with employee details --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6 flex items-center gap-4">
                    @if ($emp->user->profilePhotoUrl())
                        <img src="{{ $emp->user->profilePhotoUrl() }}" alt="{{ $emp->user->name }}" class="h-14 w-14 rounded-full object-cover border border-gray-200">
                    @else
                        <span class="h-14 w-14 rounded-full flex items-center justify-center text-white text-xl font-bold" style="background-color:#2f80ed;">{{ $emp->user->initial() }}</span>
                    @endif
                    <div>
                        <p class="text-lg text-gray-900">Welcome back, <span class="font-semibold">{{ $emp->user->name }}</span>!</p>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ $emp->designation }} &middot; {{ $emp->department }} &middot;
                            <span class="font-medium" style="color:#2f80ed;">{{ $emp->employee_code }}</span>
                        </p>
                    </div>
                </div>

                {{-- 2. Statistics cards --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white shadow-sm sm:rounded-lg p-5 border-l-4 {{ $empDash['isPresent'] ? 'border-green-400' : 'border-red-400' }}">
                        <div class="text-xs text-gray-500 uppercase">Present Status</div>
                        <div class="mt-1 text-2xl font-bold {{ $empDash['isPresent'] ? 'text-green-700' : 'text-red-700' }}">{{ $empDash['isPresent'] ? 'Present' : 'Absent' }}</div>
                    </div>
                    <div class="bg-white shadow-sm sm:rounded-lg p-5 border-l-4 border-indigo-400">
                        <div class="text-xs text-gray-500 uppercase">Today's Productive Hours</div>
                        <div class="mt-1 text-2xl font-bold text-gray-900">{{ intdiv($empDash['todayProductive'], 60) }}h {{ str_pad((string) ($empDash['todayProductive'] % 60), 2, '0', STR_PAD_LEFT) }}m</div>
                    </div>
                    <div class="bg-white shadow-sm sm:rounded-lg p-5 border-l-4 border-blue-400">
                        <div class="text-xs text-gray-500 uppercase">Leave Balance</div>
                        <div class="mt-1 text-2xl font-bold text-gray-900">{{ $empDash['leaveRemaining'] }} <span class="text-sm font-normal text-gray-500">days</span></div>
                    </div>
                    <a href="{{ route('leaves.index') }}" class="block bg-white shadow-sm sm:rounded-lg p-5 border-l-4 border-yellow-400 hover:shadow-md transition">
                        <div class="text-xs text-gray-500 uppercase">Pending Leave Requests</div>
                        <div class="mt-1 text-2xl font-bold text-gray-900">{{ $empDash['pendingLeaves'] }}</div>
                    </a>
                </div>

                {{-- 3. Attendance Summary --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Attendance Summary</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="rounded-lg p-4" style="background-color:#f5fbff;">
                            <div class="text-xs text-gray-500 uppercase">This Week</div>
                            <div class="mt-1 text-xl font-bold text-gray-900">{{ intdiv($empDash['weekProductive'], 60) }}h {{ str_pad((string) ($empDash['weekProductive'] % 60), 2, '0', STR_PAD_LEFT) }}m</div>
                            <div class="text-xs text-gray-400">productive hours</div>
                        </div>
                        <div class="rounded-lg p-4" style="background-color:#f5fbff;">
                            <div class="text-xs text-gray-500 uppercase">This Month</div>
                            <div class="mt-1 text-xl font-bold text-gray-900">{{ intdiv($empDash['monthProductive'], 60) }}h {{ str_pad((string) ($empDash['monthProductive'] % 60), 2, '0', STR_PAD_LEFT) }}m</div>
                            <div class="text-xs text-gray-400">productive hours</div>
                        </div>
                        <div class="rounded-lg p-4" style="background-color:#f5fbff;">
                            <div class="text-xs text-gray-500 uppercase">Present Days</div>
                            <div class="mt-1 text-xl font-bold text-gray-900">{{ $empDash['presentDaysMonth'] }}</div>
                            <div class="text-xs text-gray-400">this month</div>
                        </div>
                    </div>
                </div>

                {{-- 4. Recent Leave Requests --}}
                <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800">Recent Leave Requests</h3>
                        <a href="{{ route('leaves.index') }}" class="text-sm hover:underline" style="color:#2f80ed;">View all</a>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-3">Type</th>
                                <th class="px-6 py-3">Session</th>
                                <th class="px-6 py-3">Dates</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                            @forelse ($empDash['recentLeaves'] as $leave)
                                <tr>
                                    <td class="px-6 py-4">{{ $leave->typeLabel() }}</td>
                                    <td class="px-6 py-4">{{ $leave->sessionLabel() }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $leave->start_date->format('d M') }} – {{ $leave->end_date->format('d M Y') }}</td>
                                    <td class="px-6 py-4"><x-leave-status-badge :status="$leave->status" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">No leave requests yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- 5. Calendar | Schedule & Holidays | Birthdays --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">

                    {{-- Calendar --}}
                    <div class="bg-white shadow-sm rounded-2xl p-5"
                         x-data="hrmsCalendar({{ Js::from($empDash['calendar']) }}, '{{ today()->toDateString() }}')">

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
                            @forelse ($empDash['agenda'] as $item)
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

                    {{-- Birthdays --}}
                    @php($birthdays = $empDash['birthdays'])
                    @php($thisMonthCount = $birthdays->filter(fn ($b) => $b['date']->isSameMonth(now()))->count())
                    <div class="bg-white shadow-sm rounded-2xl p-5 lg:col-span-2 xl:col-span-1" x-data="{ scope: 'month' }">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-gray-800">Birthdays</h3>
                            <select x-model="scope"
                                    class="text-xs rounded-lg border-gray-200 py-1 pl-2 pr-7 text-gray-600 focus:ring-0 focus:border-gray-300">
                                <option value="month">This Month</option>
                                <option value="all">All Upcoming</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            @forelse ($birthdays as $b)
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
                                           style="background-color:#2f80ed;">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zM5 12h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                                            Send Wish
                                        </a>
                                    @else
                                        <span class="shrink-0 text-xs text-gray-400 whitespace-nowrap">
                                            {{ $b['daysUntil'] === 1 ? 'Tomorrow' : $b['date']->format('d/m/Y') }}
                                        </span>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No upcoming birthdays.</p>
                            @endforelse

                            @if ($birthdays->isNotEmpty() && $thisMonthCount === 0)
                                <p class="text-sm text-gray-500" x-show="scope === 'month'">No birthdays this month.</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- 7. Recent Activity --}}
                <x-recent-activity :activities="$empDash['recentActivity']" title="My Recent Activity" />

                {{-- 6. My Goals | Upcoming | Company Announcements --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">

                <div class="bg-white shadow-sm rounded-2xl p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">My Goals</h3>
                    <div class="space-y-4">
                        @forelse ($empDash['myGoals'] as $goal)
                            @php($overdue = $goal->isOverdue())
                            <div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-medium text-gray-900 truncate">{{ $goal->title }}</span>
                                    <span class="text-sm font-semibold text-gray-900 shrink-0">{{ $goal->progress }}%</span>
                                </div>
                                <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-full rounded-full"
                                         style="width: {{ $goal->progress }}%; background-color: {{ $overdue ? '#ef4444' : '#2f80ed' }};"></div>
                                </div>
                                @if ($goal->due_date)
                                    <div class="mt-1 text-xs {{ $overdue ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                                        Due {{ $goal->due_date->format('d M Y') }}@if ($overdue) &middot; overdue @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No goals assigned yet.</p>
                        @endforelse
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-2xl p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Upcoming</h3>
                    <div class="space-y-1">
                        @forelse ($empDash['upcoming'] as $item)
                            @php($days = (int) today()->diffInDays($item['date'], false))
                            @php($tint = $item['type'] === 'birthday' ? 'bg-pink-100 text-pink-700' : ($item['type'] === 'leave' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-800'))
                            <div class="flex items-center gap-3 rounded-xl px-2 py-2 -mx-2 hover:bg-gray-50">
                                <span class="h-9 w-9 shrink-0 rounded-xl flex items-center justify-center {{ $tint }}">
                                    @if ($item['type'] === 'birthday')
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3-1.343-3-3 0-1.5 3-5 3-5s3 3.5 3 5c0 1.657-1.343 3-3 3zm-8 5a2 2 0 012-2h12a2 2 0 012 2v7H4v-7zm0 4c2 0 2 1.5 4 1.5S12 17 12 17s0 1.5 2 1.5 2-1.5 4-1.5"/></svg>
                                    @elseif ($item['type'] === 'leave')
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    @else
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                                    @endif
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gray-900 truncate">{{ $item['label'] }}</div>
                                    <div class="text-xs text-gray-500 truncate">
                                        {{ $item['date']->format('d M Y') }}@if ($item['meta']) &middot; {{ $item['meta'] }}@endif
                                    </div>
                                </div>
                                <span class="shrink-0 text-xs font-medium whitespace-nowrap {{ $days <= 7 ? 'text-gray-900' : 'text-gray-400' }}">
                                    @if ($days === 0)
                                        Today
                                    @elseif ($days === 1)
                                        Tomorrow
                                    @elseif ($days < 30)
                                        in {{ $days }} days
                                    @else
                                        in {{ (int) round($days / 30) }} mo
                                    @endif
                                </span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Nothing coming up.</p>
                        @endforelse
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-2xl p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Company Announcements</h3>
                    <div class="space-y-4">
                        @forelse ($empDash['announcements'] as $a)
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

            @else
                {{-- No employee profile: quick links + hint --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <a href="{{ route('profile.edit') }}" class="block bg-white shadow-sm sm:rounded-lg p-6 hover:shadow-md transition">
                        <h3 class="font-semibold text-gray-900">My Profile</h3>
                        <p class="mt-1 text-sm text-gray-600">Update your name, email and password.</p>
                    </a>
                    @if (Auth::user()->hasAnyRole(['admin', 'hr']))
                        <a href="{{ route('hr.dashboard') }}" class="block bg-white shadow-sm sm:rounded-lg p-6 hover:shadow-md transition border-l-4 border-indigo-400">
                            <h3 class="font-semibold text-gray-900">HR Area</h3>
                            <p class="mt-1 text-sm text-gray-600">Human-resources tools.</p>
                        </a>
                    @endif
                </div>
                <div class="bg-blue-50 text-blue-800 text-sm sm:rounded-lg p-4">
                    You don't have an employee profile yet. Your dashboard widgets will appear once HR sets up your record.
                </div>
            @endif

            @endif {{-- end admin/non-admin branch --}}

        </div>
    </div>
</x-app-layout>
