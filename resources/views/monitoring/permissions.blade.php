@section('title', 'Monitoring Access')

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Monitoring Access') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-md bg-blue-50 border border-blue-200 p-4">
                <p class="text-sm text-blue-900">
                    Being an administrator of the HRMS does not by itself grant sight of monitoring
                    data. Access comes from this page and nowhere else, and somebody with monitoring
                    access cannot use it to clear anybody else — only an administrator can open this
                    screen. Every change here is recorded.
                </p>
            </div>

            {{-- Who currently has access --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">People with access</h3>
                </div>

                @forelse ($permissions as $permission)
                    <form method="POST" action="{{ route('monitoring.permissions.update', $permission) }}"
                          class="px-5 py-4 {{ ! $loop->last ? 'border-b border-gray-100' : '' }}">
                        @csrf
                        @method('PATCH')

                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <span class="font-medium text-gray-800">{{ $permission->user?->name ?? 'Deleted user' }}</span>
                                <span class="ml-2 text-xs uppercase text-gray-400">{{ $permission->user?->role }}</span>
                                <span class="block text-xs text-gray-400">{{ $permission->user?->email }}</span>
                                @if ($permission->grantedBy)
                                    <span class="block text-xs text-gray-400 mt-0.5">
                                        last set by {{ $permission->grantedBy->name }}
                                        @if ($permission->grantedBy->id === $permission->user_id)
                                            <span class="text-amber-600">(themselves)</span>
                                        @endif
                                    </span>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                <x-primary-button>Save</x-primary-button>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach ($capabilities as $column => $label)
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="{{ $column }}" value="1"
                                           @checked($permission->{$column})
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </form>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-gray-500">
                        Nobody has monitoring access yet. Until somebody does, the productivity
                        screens are closed to everyone — including administrators.
                    </p>
                @endforelse
            </div>

            {{-- Removing access, kept apart from the save buttons above so a
                 mis-click cannot revoke somebody. --}}
            @if ($permissions->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <h3 class="font-semibold text-gray-800 mb-3">Remove access</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($permissions as $permission)
                            <form method="POST" action="{{ route('monitoring.permissions.destroy', $permission) }}">
                                @csrf
                                @method('DELETE')
                                <button class="px-3 py-1.5 rounded-lg border border-red-200 text-sm text-red-700 hover:bg-red-50">
                                    Remove {{ $permission->user?->name ?? 'user' }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Grant --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-5">
                <h3 class="font-semibold text-gray-800 mb-4">Grant access</h3>

                @if ($candidates->isEmpty())
                    <p class="text-sm text-gray-500">
                        Every administrator, HR user and manager already has an entry above.
                    </p>
                @else
                    <form method="POST" action="{{ route('monitoring.permissions.store') }}">
                        @csrf

                        <div class="sm:w-2/3">
                            <x-input-label for="user_id" value="Person" />
                            <select id="user_id" name="user_id" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="">Choose someone…</option>
                                @foreach ($candidates as $candidate)
                                    <option value="{{ $candidate->id }}" @selected(old('user_id') == $candidate->id)>
                                        {{ $candidate->name }} ({{ $candidate->role }}) — {{ $candidate->email }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('user_id')" class="mt-1" />
                        </div>

                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach ($capabilities as $column => $label)
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="{{ $column }}" value="1" @checked(old($column))
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>

                        <p class="mt-3 text-xs text-gray-500">
                            Leaving every box unticked still grants the overview and tracking
                            screens; the boxes add the more intrusive capabilities on top.
                        </p>

                        <div class="mt-4">
                            <x-primary-button>Grant access</x-primary-button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
