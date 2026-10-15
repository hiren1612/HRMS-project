@section('title', 'HRMS Overview')

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('HRMS Overview') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Summary stat cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">

                <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-gray-900">{{ $stats['employees'] }}</div>
                        <div class="text-xs text-gray-500 uppercase tracking-wide">Employees</div>
                    </div>
                    <span class="h-10 w-10 rounded-lg flex items-center justify-center" style="background-color: #eaf5fe; color: #2f80ed;">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </span>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-gray-900">{{ $stats['pendingLeaves'] }}</div>
                        <div class="text-xs text-gray-500 uppercase tracking-wide">Pending Leaves</div>
                    </div>
                    <span class="h-10 w-10 rounded-lg flex items-center justify-center bg-yellow-100 text-yellow-800">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-gray-900">{{ $stats['approvedLeaves'] }}</div>
                        <div class="text-xs text-gray-500 uppercase tracking-wide">Approved Leaves</div>
                    </div>
                    <span class="h-10 w-10 rounded-lg flex items-center justify-center bg-green-100 text-green-800">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-gray-900">{{ $stats['departments'] }}</div>
                        <div class="text-xs text-gray-500 uppercase tracking-wide">Departments</div>
                    </div>
                    <span class="h-10 w-10 rounded-lg flex items-center justify-center bg-blue-100 text-blue-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                {{-- Headcount by department, with a bar showing relative size --}}
                @php($maxDept = max(1, (int) $departmentBreakdown->max('total')))
                <div class="lg:col-span-5 bg-white rounded-2xl shadow-sm">
                    <div class="px-6 py-4 border-b">
                        <h3 class="font-semibold text-gray-800">Headcount by Department</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        @forelse ($departmentBreakdown as $row)
                            <div>
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="text-gray-700 truncate">{{ $row->department ?: '—' }}</span>
                                    <span class="font-semibold text-gray-900">{{ $row->total }}</span>
                                </div>
                                <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-full rounded-full"
                                         style="width: {{ round($row->total / $maxDept * 100) }}%; background-color: #2f80ed;"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No departments yet.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Recent leave requests --}}
                <div class="lg:col-span-7 bg-white rounded-2xl shadow-sm">
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800">Recent Leave Requests</h3>
                        <a href="{{ route('leaves.manage') }}" class="text-sm font-medium hover:underline" style="color: #2f80ed;">View all</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="rt min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <th class="px-6 py-3">Employee</th>
                                    <th class="px-6 py-3">Type</th>
                                    <th class="px-6 py-3">Dates</th>
                                    <th class="px-6 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                                @forelse ($recentLeaves as $leave)
                                    <tr class="hover:bg-gray-50">
                                        <td data-label="Employee" class="px-6 py-4 font-medium text-gray-900">{{ $leave->employee->displayName() }}</td>
                                        <td data-label="Type" class="px-6 py-4">{{ $leave->typeLabel() }}</td>
                                        <td data-label="Dates" class="px-6 py-4 text-gray-500 whitespace-nowrap">
                                            {{ $leave->start_date->asShortDate() }} – {{ $leave->end_date->asDate() }}
                                        </td>
                                        <td data-label="Status" class="px-6 py-4"><x-leave-status-badge :status="$leave->status" /></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">No leave requests yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
