<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Meeting Schedules') }}</h2>
            <x-page-action :href="route('schedules.create')">+ New Meeting</x-page-action>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="rt min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Meeting</th>
                            <th class="px-6 py-3">Date</th>
                            <th class="px-6 py-3">Time</th>
                            <th class="px-6 py-3">Link</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse ($schedules as $schedule)
                            <tr>
                                <td data-label="Meeting" class="px-6 py-4">
                                    @if ($schedule->role_tag)
                                        <span class="inline-block mb-1 px-2 py-0.5 rounded text-xs font-semibold bg-gray-800 text-white">{{ $schedule->role_tag }}</span>
                                    @endif
                                    <div class="font-medium text-gray-900">{{ $schedule->title }}</div>
                                </td>
                                <td data-label="Date" class="px-6 py-4 whitespace-nowrap">{{ $schedule->meeting_date->asDateWithDay() }}</td>
                                <td data-label="Time" class="px-6 py-4 whitespace-nowrap">{{ $schedule->timeRange() }}</td>
                                <td data-label="Link" class="px-6 py-4">
                                    @if ($schedule->meeting_link)
                                        <a href="{{ $schedule->meeting_link }}" target="_blank" rel="noopener"
                                           class="hover:underline" style="color:#2f80ed;">Join</a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td data-label="" class="px-6 py-4 text-right whitespace-nowrap">
                                    <a href="{{ route('schedules.edit', $schedule) }}" class="hover:underline" style="color:#2f80ed;">Edit</a>
                                    <span class="ml-3">
                                        <x-confirm-action :action="route('schedules.destroy', $schedule)"
                                                          title="Delete this meeting?"
                                                          :message="'&quot;' . $schedule->title . '&quot; on ' . $schedule->meeting_date->asDate() . ' will be removed from every dashboard.'" />
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No meetings scheduled yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $schedules->links() }}</div>
        </div>
    </div>
</x-app-layout>
