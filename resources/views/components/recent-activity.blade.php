@props(['activities', 'title' => 'Recent Activity'])

<div class="bg-white shadow-sm rounded-2xl p-5">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-gray-800">{{ $title }}</h3>
        <a href="{{ route('activity.index') }}" class="text-sm font-medium hover:underline" style="color:#2f80ed;">View all</a>
    </div>

    <div class="space-y-1">
        @forelse ($activities as $activity)
            <div class="flex items-start gap-3 rounded-xl px-2 py-2 -mx-2 hover:bg-gray-50">
                <span class="h-8 w-8 shrink-0 rounded-xl flex items-center justify-center {{ $activity->tone() }}">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="text-sm text-gray-900 truncate">{{ $activity->description }}</div>
                    <div class="text-xs text-gray-500">{{ $activity->created_at->diffForHumans() }}</div>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">No activity recorded yet.</p>
        @endforelse
    </div>
</div>
