@section('title', 'Leave Requests')

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Pending Leave Requests') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="rt min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Employee</th>
                            <th class="px-6 py-3">Type</th>
                            <th class="px-6 py-3">Session</th>
                            <th class="px-6 py-3">Dates</th>
                            <th class="px-6 py-3">Reason</th>
                            <th class="px-6 py-3 text-right">Decision</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse ($pendingLeaves as $leave)
                            <tr>
                                <td data-label="Employee" class="px-6 py-4">{{ $leave->employee->displayName() }}</td>
                                <td data-label="Type" class="px-6 py-4">{{ $leave->typeLabel() }}</td>
                                <td data-label="Session" class="px-6 py-4">{{ $leave->sessionLabel() }}</td>
                                <td data-label="Dates" class="px-6 py-4 whitespace-nowrap">
                                    {{ $leave->start_date->asShortDate() }} – {{ $leave->end_date->asDate() }}
                                </td>
                                <td data-label="Reason" class="px-6 py-4 max-w-xs truncate" title="{{ $leave->reason }}">{{ $leave->reason ?? '—' }}</td>
                                <td data-label="Decision" class="px-6 py-4 text-right whitespace-nowrap">
                                    @can('approve', $leave)
                                        <div class="inline-flex items-center gap-2">
                                            <x-confirm-action :action="route('leaves.approve', $leave)" method="PATCH"
                                                              label="Approve" confirm="Approve leave" tone="primary"
                                                              title="Approve this leave request?"
                                                              :message="$leave->employee->displayName() . ' will be approved for ' . $leave->dayCount() . ' day(s), and the days deducted from their balance.'" />
                                            {{-- QA M3: a rejection carries a reason the employee can read. --}}
                                            <div x-data="{ open: false }" class="inline-block">
                                                <button type="button" x-on:click="open = !open"
                                                        class="px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-500">Reject</button>
                                                <form method="POST" action="{{ route('leaves.reject', $leave) }}"
                                                      x-show="open" x-cloak class="mt-2 flex items-start gap-2">
                                                    @csrf @method('PATCH')
                                                    <input type="text" name="decision_note" required maxlength="500"
                                                           placeholder="Reason for rejecting"
                                                           class="w-56 text-xs border-gray-300 rounded-md shadow-sm">
                                                    <button class="px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-500">Confirm</button>
                                                </form>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400 italic">Your own request</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">No pending leave requests.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $pendingLeaves->links() }}</div>
        </div>
    </div>
</x-app-layout>
