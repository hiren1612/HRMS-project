@section('title', 'Goals')

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Goals') }}</h2>
            <x-page-action :href="route('goals.create')">+ Assign Goal</x-page-action>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            <div class="bg-white shadow-sm rounded-2xl overflow-x-auto">
                <table class="rt min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Employee</th>
                            <th class="px-6 py-3">Goal</th>
                            <th class="px-6 py-3 w-56">Progress</th>
                            <th class="px-6 py-3">Due</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse ($goals as $goal)
                            <tr class="hover:bg-gray-50">
                                <td data-label="Employee" class="px-6 py-4">
                                    <div class="font-medium text-gray-900">{{ $goal->employee->displayName() }}</div>
                                    <div class="text-xs text-gray-500">{{ $goal->employee->employee_code }}</div>
                                </td>
                                <td data-label="Goal" class="px-6 py-4">
                                    <div class="font-medium text-gray-900">{{ $goal->title }}</div>
                                    @if ($goal->description)
                                        <div class="text-xs text-gray-500 max-w-md truncate">{{ $goal->description }}</div>
                                    @endif
                                </td>
                                <td data-label="Progress" class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                            <div class="h-full rounded-full"
                                                 style="width: {{ $goal->progress }}%; background-color: #2f80ed;"></div>
                                        </div>
                                        <span class="w-10 text-right text-xs font-semibold text-gray-900">{{ $goal->progress }}%</span>
                                    </div>
                                </td>
                                <td data-label="Due" class="px-6 py-4 whitespace-nowrap">
                                    @if ($goal->due_date)
                                        <span class="{{ $goal->isOverdue() ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                            {{ $goal->due_date->asDate() }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td data-label="Status" class="px-6 py-4">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold capitalize
                                        {{ $goal->status === 'completed' ? 'bg-green-100 text-green-800'
                                           : ($goal->status === 'cancelled' ? 'bg-gray-200 text-gray-700' : 'bg-blue-100 text-blue-700') }}">
                                        {{ $goal->status }}
                                    </span>
                                </td>
                                <td data-label="" class="px-6 py-4 text-right whitespace-nowrap">
                                    <a href="{{ route('goals.edit', $goal) }}" class="font-medium hover:underline" style="color:#2f80ed;">Edit</a>
                                    <span class="ml-3">
                                        <x-confirm-action :action="route('goals.destroy', $goal)"
                                                          title="Delete this goal?"
                                                          :message="'&quot;' . $goal->title . '&quot; and its progress will be removed. This cannot be undone.'" />
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">
                                    No goals yet. Use <span class="font-semibold">Assign Goal</span> to create the first one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $goals->links() }}</div>
        </div>
    </div>
</x-app-layout>
