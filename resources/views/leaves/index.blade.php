<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('My Leaves') }}</h2>
            @can('create', \App\Models\Leave::class)
                <a href="{{ route('leaves.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-xs font-semibold uppercase rounded-md hover:bg-gray-700">
                    + Apply for Leave
                </a>
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

            <div class="bg-white shadow-sm sm:rounded-lg overflow-y-hidden whitespace-nowrap overflow-x-auto w-100">
                <table class="min-w-full divide-y divide-gray-200">
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
                                <td class="px-6 py-4">{{ $leave->typeLabel() }}</td>
                                <td class="px-6 py-4">{{ $leave->sessionLabel() }}</td>
                                <td class="px-6 py-4">{{ $leave->start_date->format('d M Y') }}</td>
                                <td class="px-6 py-4">{{ $leave->end_date->format('d M Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ rtrim(rtrim(number_format($leave->dayCount(), 1), '0'), '.') }}</td>
                                <td class="px-6 py-4 max-w-xs truncate" title="{{ $leave->reason }}">{{ $leave->reason ?? '—' }}</td>
                                <td class="px-6 py-4">
                                    <x-leave-status-badge :status="$leave->status" />
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('leaves.show', $leave) }}" class="text-indigo-600 hover:underline">View</a>
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
