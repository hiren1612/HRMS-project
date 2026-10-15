@section('title', 'Announcements')

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Announcements') }}</h2>
            <x-page-action :href="route('announcements.create')">+ New Announcement</x-page-action>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            <p class="mb-4 text-sm text-gray-600">
                Published announcements appear on every dashboard. Unpublish one to hide it
                without losing it, or give it an end date so it drops off by itself.
            </p>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="rt min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Title</th>
                            <th class="px-6 py-3">Message</th>
                            <th class="px-6 py-3">Posted</th>
                            <th class="px-6 py-3">Shows until</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse ($announcements as $announcement)
                            <tr>
                                <td data-label="Title" class="px-6 py-4 font-medium text-gray-900">{{ $announcement->title }}</td>
                                <td data-label="Message" class="px-6 py-4 text-gray-600">
                                    {{ Str::limit($announcement->body, 80) ?: '—' }}
                                </td>
                                <td data-label="Posted" class="px-6 py-4 whitespace-nowrap">{{ $announcement->created_at->asDate() }}</td>
                                <td data-label="Shows until" class="px-6 py-4 whitespace-nowrap">
                                    {{ $announcement->expires_at?->asDate() ?? 'No end date' }}
                                </td>
                                <td data-label="Status" class="px-6 py-4">
                                    @php($hidden = $announcement->hiddenReason())
                                    @if ($hidden)
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-200 text-gray-700">{{ $hidden }}</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">On dashboards</span>
                                    @endif
                                </td>
                                <td data-label="" class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
                                    <a href="{{ route('announcements.edit', $announcement) }}" class="text-sm font-medium text-blue-600 hover:underline">Edit</a>
                                    <x-confirm-action :action="route('announcements.destroy', $announcement)"
                                                      title="Delete this announcement?"
                                                      :message="'&quot;' . $announcement->title . '&quot; will be removed from every dashboard. This cannot be undone.'" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    No announcements yet. Click “New Announcement” to post one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $announcements->links() }}</div>
        </div>
    </div>
</x-app-layout>
