<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Pending Leave Requests') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-y-hidden whitespace-nowrap overflow-x-auto w-100">
                <table class="min-w-full divide-y divide-gray-200">
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
                                <td class="px-6 py-4">{{ $leave->employee->displayName() }}</td>
                                <td class="px-6 py-4">{{ $leave->typeLabel() }}</td>
                                <td class="px-6 py-4">{{ $leave->sessionLabel() }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $leave->start_date->format('d M') }} – {{ $leave->end_date->format('d M Y') }}
                                </td>
                                <td class="px-6 py-4 max-w-xs truncate" title="{{ $leave->reason }}">{{ $leave->reason ?? '—' }}</td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    @can('approve', $leave)
                                        <div class="inline-flex items-center gap-2">
                                            <form method="POST" action="{{ route('leaves.approve', $leave) }}"
                                                  onsubmit="return confirm('Approve this leave?');">
                                                @csrf @method('PATCH')
                                                <button class="px-3 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-500">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('leaves.reject', $leave) }}"
                                                  onsubmit="return confirm('Reject this leave?');">
                                                @csrf @method('PATCH')
                                                <button class="px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-500">Reject</button>
                                            </form>
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
