@section('title', 'Activity Log')

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Activity Log') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Filters --}}
            <form method="GET" class="bg-white shadow-sm rounded-2xl p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 {{ $seesEveryone ? 'xl:grid-cols-5' : 'xl:grid-cols-4' }} gap-4">
                    <div>
                        <x-input-label for="search" :value="__('Search')" />
                        <x-text-input id="search" name="search" type="search" class="mt-1 block w-full"
                                      :value="request('search')" placeholder="What happened…" />
                    </div>

                    <div>
                        <x-input-label for="action" :value="__('Type')" />
                        <select id="action" name="action" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option value="">All types</option>
                            @foreach ($types as $type)
                                <option value="{{ $type }}" @selected(request('action') === $type)>{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($seesEveryone)
                        <div>
                            <x-input-label for="user" :value="__('Person')" />
                            <select id="user" name="user" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Everyone</option>
                                @foreach ($people as $id => $name)
                                    <option value="{{ $id }}" @selected((string) request('user') === (string) $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <x-input-label for="from" :value="__('From')" />
                        <x-text-input id="from" name="from" type="date" class="mt-1 block w-full" :value="request('from')" />
                    </div>

                    <div>
                        <x-input-label for="to" :value="__('To')" />
                        <x-text-input id="to" name="to" type="date" class="mt-1 block w-full" :value="request('to')" />
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <x-primary-button>Filter</x-primary-button>
                    @if (request()->hasAny(['search', 'action', 'user', 'from', 'to']))
                        <a href="{{ route('activity.index') }}" class="text-sm text-gray-600 hover:underline">Clear</a>
                    @endif
                    <span class="ml-auto text-sm text-gray-500">{{ $activities->total() }} {{ Str::plural('entry', $activities->total()) }}</span>
                </div>
            </form>

            {{-- Timeline --}}
            <div class="bg-white shadow-sm rounded-2xl p-5">
                @forelse ($activities as $activity)
                    <div class="flex items-start gap-3 py-3 {{ ! $loop->last ? 'border-b border-gray-100' : '' }}">
                        <span class="h-9 w-9 shrink-0 rounded-xl flex items-center justify-center {{ $activity->tone() }}">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm text-gray-900">{{ $activity->description }}</div>
                            <div class="text-xs text-gray-500">
                                <span class="font-mono">{{ $activity->action }}</span>
                                @if ($seesEveryone && $activity->user)
                                    &middot; {{ $activity->user->name }}
                                @endif
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="text-xs text-gray-500 whitespace-nowrap">{{ $activity->created_at->diffForHumans() }}</div>
                            <div class="text-xs text-gray-400 whitespace-nowrap">{{ $activity->created_at->asDateTime() }}</div>
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-gray-500">
                        @if (request()->hasAny(['search', 'action', 'user', 'from', 'to']))
                            Nothing matches those filters.
                        @else
                            No activity recorded yet. Entries appear here as people use the system.
                        @endif
                    </p>
                @endforelse
            </div>

            <div>{{ $activities->links() }}</div>
        </div>
    </div>
</x-app-layout>
