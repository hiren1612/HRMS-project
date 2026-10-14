<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Notifications') }}</h2>
            @if (Auth::user()->unreadNotifications->count() > 0)
                <form method="POST" action="{{ route('notifications.readAll') }}">
                    @csrf
                    <button class="text-sm text-indigo-600 hover:underline">Mark all read</button>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100">
                @forelse ($notifications as $notification)
                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                    @csrf
                    <button type="submit"
                       class="w-full text-left flex items-start gap-3 px-6 py-4 hover:bg-gray-50 {{ $notification->read_at ? '' : 'bg-indigo-50/40' }}">
                        {{-- Unread dot --}}
                        <span class="mt-1 h-2 w-2 rounded-full {{ $notification->read_at ? 'bg-transparent' : 'bg-indigo-500' }}"></span>
                        <div class="flex-1">
                            <div class="text-sm text-gray-800">{{ $notification->data['message'] ?? 'Notification' }}</div>
                            <div class="text-xs text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</div>
                        </div>
                    </button>
                    </form>
                @empty
                    <div class="px-6 py-10 text-center text-gray-500">You have no notifications.</div>
                @endforelse
            </div>

            <div class="mt-4">{{ $notifications->links() }}</div>
        </div>
    </div>
</x-app-layout>
