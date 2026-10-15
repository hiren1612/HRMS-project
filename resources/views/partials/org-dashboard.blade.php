{{-- Shared organisation overview (used by Admin Dashboard and HR Dashboard). Expects $dash. --}}
@php($palette = ['#8acbf8', '#5fa8e6', '#3b82f6', '#2563eb', '#1e40af', '#93c5fd', '#60a5fa'])

<div class="space-y-6">

    {{-- Top area: hero | (stats + performance) | employee donut --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- Hero card --}}
        <div class="lg:col-span-3 rounded-2xl p-6 flex flex-col justify-between" style="background-color: #eaf5fe;">
            @if (Auth::user()->profilePhotoUrl())
                <img src="{{ Auth::user()->profilePhotoUrl() }}" alt="{{ Auth::user()->name }}"
                     class="h-28 w-28 mx-auto rounded-full object-cover border-4 border-white shadow">
            @else
                <div class="h-28 w-28 mx-auto rounded-full flex items-center justify-center" style="background-color: #8acbf8;">
                    <svg class="h-14 w-14 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
            @endif
            {{-- QA U9: the greeting was shouted in capitals with a stray space
                 before the exclamation mark, and the card asked you to review
                 leave requests even when there were none. --}}
            <div class="mt-4 text-center">
                <h3 class="text-lg font-bold text-gray-900">
                    {{ now()->hour < 12 ? 'Good morning' : (now()->hour < 17 ? 'Good afternoon' : 'Good evening') }},
                    {{ Str::before(Auth::user()->name, ' ') ?: Auth::user()->name }}
                </h3>
                <p class="mt-2 text-sm text-gray-600">
                    @if ($dash['pendingTotal'] > 0)
                        <span class="font-semibold" style="color: #2563eb;">{{ $dash['pendingTotal'] }}</span>
                        leave {{ Str::plural('request', $dash['pendingTotal']) }}
                        {{ $dash['pendingTotal'] === 1 ? 'is' : 'are' }} waiting for your decision.
                    @else
                        Nothing is waiting for your decision today.
                    @endif
                </p>
            </div>

            @if ($dash['pendingTotal'] > 0)
                <a href="{{ route('leaves.manage') }}"
                   class="mt-5 inline-flex justify-center px-5 py-2 rounded-lg text-sm font-semibold text-white hover:opacity-90"
                   style="background-color: #2f80ed;">
                    Review {{ $dash['pendingTotal'] === 1 ? 'it' : 'them' }}
                </a>
            @else
                <a href="{{ route('leaves.manage') }}"
                   class="mt-5 inline-flex justify-center px-5 py-2 rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    View leave requests
                </a>
            @endif
        </div>

        {{-- Middle: stat cards + performance chart --}}
        <div class="lg:col-span-6 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-gray-900">{{ $dash['presentToday'] }}</div>
                        <div class="text-xs text-gray-500">Total Present</div>
                    </div>
                    <span class="h-10 w-10 rounded-lg flex items-center justify-center" style="background-color: #eaf5fe; color: #2f80ed;">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                </div>
                <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-gray-900">{{ $dash['absentToday'] }}</div>
                        <div class="text-xs text-gray-500">Total Absent</div>
                    </div>
                    <span class="h-10 w-10 rounded-lg flex items-center justify-center" style="background-color: #f1f5f9; color: #64748b;">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </span>
                </div>
                <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-gray-900">{{ $dash['onLeaveToday'] }}</div>
                        <div class="text-xs text-gray-500">Total On Leave</div>
                    </div>
                    <span class="h-10 w-10 rounded-lg flex items-center justify-center" style="background-color: #eaf5fe; color: #2f80ed;">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </span>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                        <span class="inline-block h-4 w-1.5 rounded-full" style="background-color:#2f80ed;"></span>
                        Employee Attendance
                    </h3>
                    <span class="inline-flex items-center gap-2 text-xs text-gray-500 border border-gray-200 rounded-lg px-3 py-1.5">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ today()->asDate() }}
                    </span>
                </div>

                {{-- Donut with total employee count in the centre --}}
                <div class="relative mx-auto" style="height: 230px; max-width: 320px;">
                    <canvas id="attendanceDonut"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-xs text-gray-500">Total Employee</span>
                        <span class="text-3xl font-bold text-gray-900">{{ $dash['totalEmployees'] }}</span>
                    </div>
                </div>

                {{-- Legend with counts (three columns side by side) --}}
                <div class="mt-4 flex rounded-xl border border-gray-100">
                    <div class="flex-1 py-3 text-center">
                        <div class="text-xs text-gray-500">On Time</div>
                        <div class="text-sm font-bold" style="color:#2f80ed;">({{ $dash['attnOnTime'] }})</div>
                    </div>
                    <div class="flex-1 py-3 text-center" style="border-left:1px solid #f3f4f6; border-right:1px solid #f3f4f6;">
                        <div class="text-xs text-gray-500">Late Arrival</div>
                        <div class="text-sm font-bold" style="color:#f5a623;">({{ $dash['attnLate'] }})</div>
                    </div>
                    <div class="flex-1 py-3 text-center">
                        <div class="text-xs text-gray-500">Absent</div>
                        <div class="text-sm font-bold" style="color:#ec4899;">({{ $dash['attnAbsent'] }})</div>
                    </div>
                </div>

                <div class="mt-3 pt-3 border-t border-gray-100 text-center">
                    <a href="{{ route('attendance.index') }}" class="text-sm font-medium hover:underline" style="color:#2f80ed;">View All Attendance</a>
                </div>
            </div>
        </div>

        {{-- Right: Total Employee donut --}}
        {{-- QA U9: the ring had an empty hole and no total, so the one number
             the card is named after was the one number it did not show. The
             figure also has to be readable with charts blocked or switched
             off, hence the plain text in the middle rather than a plugin. --}}
        @php($headcount = $dash['designations']->sum('total'))
        <div class="lg:col-span-3 bg-white rounded-2xl shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-2">Employees by Role</h3>

            <div class="relative h-44">
                <canvas id="empDonut" role="img"
                        aria-label="{{ $headcount }} employees, split by designation; the same figures are listed below."></canvas>
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-3xl font-bold leading-none text-gray-900">{{ $headcount }}</span>
                    <span class="text-xs uppercase tracking-wide text-gray-500">
                        {{ Str::plural('employee', $headcount) }}
                    </span>
                </div>
            </div>

            <div class="mt-4 space-y-2">
                @forelse ($dash['designations'] as $d)
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="inline-flex min-w-0 items-center gap-2 text-gray-700">
                            <span class="h-2.5 w-2.5 flex-none rounded-full" style="background-color: {{ $palette[$loop->index % count($palette)] }};"></span>
                            <span class="truncate" title="{{ $d->designation }}">{{ $d->designation }}</span>
                        </span>
                        <span class="flex-none font-medium text-gray-900">{{ $d->total }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No employees yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Action Center | Briefing. The partial is shared, so the panels take
         the viewer's own role: "Admin ..." for admins, "HR ..." for HR. --}}
    @php($panelRole = Auth::user()->isAdmin() ? 'Admin' : 'HR')
    @php($showHealth = Auth::user()->isAdmin())
    <div class="grid grid-cols-1 lg:grid-cols-2 {{ $showHealth ? 'xl:grid-cols-3' : '' }} gap-6">

        {{-- Action Center: counters HR can act on, each linking to where they act --}}
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <div class="flex items-center gap-2 mb-4">
                <span class="h-8 w-8 rounded-lg flex items-center justify-center bg-red-100 text-red-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </span>
                <h3 class="font-semibold text-gray-800">{{ $panelRole }} Action Center</h3>
            </div>

            <div class="space-y-1">
                @foreach ($dash['hrActions'] as $action)
                    @php($done = $action['count'] === 0)
                    <a href="{{ $action['url'] }}"
                       class="flex items-center gap-3 rounded-xl px-2 py-2.5 -mx-2 hover:bg-gray-50">
                        <span class="h-9 w-9 shrink-0 rounded-xl flex items-center justify-center text-sm font-bold
                            {{ $done ? 'bg-gray-100 text-gray-400' : ($action['tone'] === 'amber' ? 'bg-yellow-100 text-yellow-800' : ($action['tone'] === 'blue' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700')) }}">
                            {{ $action['count'] }}
                        </span>
                        <span class="min-w-0 flex-1 text-sm {{ $done ? 'text-gray-400' : 'font-medium text-gray-900' }} truncate">
                            {{ $action['label'] }}
                        </span>
                        @unless ($done)
                            <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        @endunless
                    </a>
                @endforeach
            </div>

            @if (collect($dash['hrActions'])->sum('count') === 0)
                <p class="mt-3 pt-3 border-t text-xs text-gray-500">Nothing needs your attention right now.</p>
            @endif
        </div>

        {{-- Briefing: auto-written summary lines --}}
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <div class="flex items-center gap-2 mb-4">
                <span class="h-8 w-8 rounded-lg flex items-center justify-center bg-blue-100 text-blue-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m0 0h2a2 2 0 012 2v9a2 2 0 01-2 2h-2m0-13v13M9 8h4m-4 4h4m-4 4h2"/></svg>
                </span>
                <h3 class="font-semibold text-gray-800">{{ $panelRole }} Briefing</h3>
                <span class="ml-auto text-xs text-gray-400">{{ now()->asDate() }}</span>
            </div>

            <ul class="space-y-2.5">
                @foreach ($dash['hrBriefing'] as $line)
                    <li class="flex items-start gap-2.5 text-sm text-gray-700">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full" style="background-color:#2f80ed;"></span>
                        <span>{{ $line }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- System Health (admins only): local infrastructure checks --}}
        @if ($showHealth)
            <div class="bg-white rounded-2xl shadow-sm p-5">
                <div class="flex items-center gap-2 mb-4">
                    <span class="h-8 w-8 rounded-lg flex items-center justify-center bg-green-100 text-green-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </span>
                    <h3 class="font-semibold text-gray-800">System Health</h3>
                </div>

                <div class="space-y-1">
                    @foreach ($dash['systemHealth'] as $check)
                        @php($dot = $check['status'] === 'ok' ? '#22c55e' : ($check['status'] === 'warn' ? '#f59e0b' : '#ef4444'))
                        <div class="flex items-center gap-3 rounded-xl px-2 py-2 -mx-2">
                            <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $dot }};"></span>
                            <span class="min-w-0 flex-1 text-sm text-gray-700 truncate">{{ $check['label'] }}</span>
                            <span class="shrink-0 text-xs {{ $check['status'] === 'ok' ? 'text-gray-500' : 'font-semibold' }}"
                                  @if ($check['status'] !== 'ok') style="color: {{ $dot }};" @endif>
                                {{ $check['detail'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Recent Activity across the organisation --}}
    <x-recent-activity :activities="$dash['recentActivity']" />

    {{-- Bottom area: Employee Status | Announcements | Upcoming Holidays --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- Employee Status --}}
        <div class="lg:col-span-6 bg-white rounded-2xl shadow-sm">
            <div class="px-6 py-4 border-b flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Employee Status</h3>
                <a href="{{ route('employees.index') }}" class="text-sm hover:underline" style="color:#2f80ed;">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">ID</th>
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Job role</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">View</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse ($dash['employeeList'] as $emp)
                            <tr>
                                <td class="px-6 py-4 font-medium" style="color:#2f80ed;">{{ $emp->employee_code }}</td>
                                <td class="px-6 py-4 text-gray-900">{{ $emp->displayName() }}</td>
                                <td class="px-6 py-4">{{ $emp->designation }}</td>
                                <td class="px-6 py-4">
                                    @if ($emp->status === 'active')
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold" style="background-color:#dbeefb; color:#1d6fb8;">Active</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('employees.show', $emp) }}" class="hover:underline" style="color:#2f80ed;">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No employees yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Announcements --}}
        <div class="lg:col-span-3 bg-white rounded-2xl shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4">Announcements</h3>
            <div class="space-y-4">
                @forelse ($dash['announcements'] as $a)
                    <div class="pl-3 border-l-2" style="border-color:#8acbf8;">
                        <div class="font-medium text-gray-900 text-sm">{{ $a->title }}</div>
                        @if ($a->body)
                            <div class="text-xs text-gray-600">{{ $a->body }}</div>
                        @endif
                        <div class="text-xs text-gray-400 mt-1">{{ $a->created_at->diffForHumans() }}</div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No announcements yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Upcoming Holidays --}}
        <div class="lg:col-span-3 bg-white rounded-2xl shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4">Upcoming Holidays</h3>
            <div class="space-y-3">
                @forelse ($dash['upcomingHolidays'] as $h)
                    <div class="text-sm">
                        <div class="font-medium text-gray-800">{{ $h->title }}</div>
                        <div class="text-xs text-gray-500">{{ $h->date->asDate() }} ({{ $h->date->format('D') }})</div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No upcoming holidays.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Schedules | Birthdays --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- Schedules (upcoming meetings) --}}
        <div class="lg:col-span-6 bg-white rounded-2xl shadow-sm">
            <div class="px-6 py-4 border-b flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Schedules</h3>
                <a href="{{ route('schedules.index') }}" class="text-sm hover:underline" style="color:#2f80ed;">View All</a>
            </div>
            <div class="p-6 space-y-4">
                @forelse ($dash['schedules'] as $meeting)
                    <div class="rounded-xl border border-gray-100 p-4" style="background-color:#f8fbff;">
                        @if ($meeting->role_tag)
                            <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold bg-gray-800 text-white">{{ $meeting->role_tag }}</span>
                        @endif
                        <div class="mt-2 font-semibold text-gray-900">{{ $meeting->title }}</div>
                        <div class="mt-2 flex flex-wrap items-center gap-x-8 gap-y-1 text-sm text-gray-500">
                            <span class="inline-flex items-center gap-2">
                                <svg class="h-4 w-4" style="color:#2f80ed;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                {{ $meeting->meeting_date->asDateWithDay() }}
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <svg class="h-4 w-4" style="color:#2f80ed;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ $meeting->timeRange() }}
                            </span>
                        </div>
                        @if ($meeting->meeting_link)
                            <div class="mt-3">
                                <a href="{{ $meeting->meeting_link }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center px-4 py-1.5 rounded-lg text-sm font-semibold text-white hover:opacity-90"
                                   style="background-color:#2f80ed;">Join Meeting</a>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No upcoming meetings scheduled.</p>
                @endforelse
            </div>
        </div>

        {{-- Birthdays (from employee date of birth) --}}
        <div class="lg:col-span-6 bg-white rounded-2xl shadow-sm">
            <div class="px-6 py-4 border-b flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Birthdays</h3>
                <span class="text-xl">🎂</span>
            </div>
            <div class="p-6 space-y-3">
                @forelse ($dash['birthdays'] as $b)
                    @php($emp = $b['employee'])
                    <div class="flex items-center gap-3 rounded-xl p-3 {{ $b['daysUntil'] === 0 ? 'text-white' : 'bg-gray-50' }}"
                         @if ($b['daysUntil'] === 0) style="background-color:#134e5e;" @endif>
                        @if ($emp->user?->profilePhotoUrl())
                            <img src="{{ $emp->user->profilePhotoUrl() }}" alt="{{ $emp->displayName() }}" class="h-10 w-10 rounded-full object-cover">
                        @else
                            <span class="h-10 w-10 rounded-full flex items-center justify-center text-white text-sm font-bold" style="background-color:#2f80ed;">{{ $emp->user?->initial() ?? '?' }}</span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold {{ $b['daysUntil'] === 0 ? 'text-white' : 'text-gray-900' }} truncate">{{ $emp->displayName() }}</div>
                            <div class="text-xs {{ $b['daysUntil'] === 0 ? 'text-white/80' : 'text-gray-500' }} truncate">{{ $emp->designation }}</div>
                        </div>
                        <span class="text-xs font-medium {{ $b['daysUntil'] === 0 ? 'text-white' : 'text-gray-500' }} whitespace-nowrap">
                            @if ($b['daysUntil'] === 0)
                                Today
                            @elseif ($b['daysUntil'] === 1)
                                Tomorrow
                            @else
                                {{ $b['date']->asShortDate() }}
                            @endif
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No upcoming birthdays. Add a Date of Birth to employee records.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Chart.js (blue theme) --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') return;

        const attn = document.getElementById('attendanceDonut');
        if (attn) {
            new Chart(attn, {
                type: 'doughnut',
                data: {
                    labels: ['On Time', 'Late Arrival', 'Absent'],
                    datasets: [{
                        data: [{{ $dash['attnOnTime'] }}, {{ $dash['attnLate'] }}, {{ $dash['attnAbsent'] }}],
                        backgroundColor: ['#2f80ed', '#f5a623', '#ec4899'],
                        borderWidth: 0,
                        borderRadius: 6,
                        spacing: 2
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, cutout: '72%', plugins: { legend: { display: false } } }
            });
        }

        const donut = document.getElementById('empDonut');
        if (donut) {
            new Chart(donut, {
                type: 'doughnut',
                data: {
                    labels: @json($dash['designations']->pluck('designation')),
                    datasets: [{ data: @json($dash['designations']->pluck('total')), backgroundColor: @json($palette), borderWidth: 0 }]
                },
                options: { responsive: true, maintainAspectRatio: false, cutout: '72%', plugins: { legend: { display: false } } }
            });
        }
    });
</script>
