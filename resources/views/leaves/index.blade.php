@section('title', 'My Leaves')

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('My Leaves') }}</h2>
            @can('create', \App\Models\Leave::class)
                <x-page-action :href="route('leaves.create')">+ Apply for Leave</x-page-action>
            @endcan
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            {{-- Users without an employee profile can't apply --}}
            @unless ($employee)
                <div class="mb-4 rounded-md bg-yellow-50 p-4 text-sm text-yellow-800">
                    You don't have an employee profile yet, so you can't apply for leave.
                    Please ask HR to set up your employee record.
                </div>
            @endunless

            {{-- Leave balance (remaining auto-deducts as approved leaves are taken) --}}
            <div class="mb-6">
                <x-leave-balance :employee="$employee" />
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="rt min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Type</th>
                            <th class="px-6 py-3">Session</th>
                            <th class="px-6 py-3">From</th>
                            <th class="px-6 py-3">To</th>
                            <th class="px-6 py-3">Days</th>
                            <th class="px-6 py-3">Reason</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse ($leaves as $leave)
                            <tr>
                                <td data-label="Type" class="px-6 py-4">{{ $leave->typeLabel() }}</td>
                                <td data-label="Session" class="px-6 py-4">{{ $leave->sessionLabel() }}</td>
                                <td data-label="From" class="px-6 py-4">{{ $leave->start_date->asDate() }}</td>
                                <td data-label="To" class="px-6 py-4">{{ $leave->end_date->asDate() }}</td>
                                <td data-label="Days" class="px-6 py-4 whitespace-nowrap">{{ rtrim(rtrim(number_format($leave->dayCount(), 1), '0'), '.') }}</td>
                                <td data-label="Reason" class="px-6 py-4 max-w-xs truncate" title="{{ $leave->reason }}">{{ $leave->reason ?? '—' }}</td>
                                <td data-label="Status" class="px-6 py-4">
                                    <x-leave-status-badge :status="$leave->status" />
                                </td>
                                <td data-label="" class="px-6 py-4 text-right">
                                    <a href="{{ route('leaves.show', $leave) }}" class="text-indigo-600 hover:underline">View</a>
                                    @if ($leave->isPending())
                                        {{-- QA M4: withdraw a request that has not been decided. --}}
                                        <span class="ml-3">
                                            <x-confirm-action :action="route('leaves.cancel', $leave)" method="PATCH"
                                                              label="Cancel" confirm="Withdraw request"
                                                              title="Withdraw this leave request?"
                                                              message="The request will be withdrawn and the days returned to your balance." />
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    You haven't requested any leave yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $leaves->links() }}</div>
        </div>
    </div>
</x-app-layout>
