@section('title', 'Live Screen')

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Live Screen') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @unless ($liveEnabled)
                <div class="rounded-md bg-amber-50 border border-amber-200 p-4 text-sm text-amber-900">
                    <span class="font-semibold">Live viewing is switched off.</span>
                    Turn it on in monitoring settings before requesting a session.
                </div>
            @endunless

            {{-- Always visible, because somebody opening this page should
                 read it before they start rather than after. --}}
            <div class="rounded-md bg-blue-50 border border-blue-200 p-4 text-sm text-blue-900">
                <p class="font-semibold">The employee will know.</p>
                <p class="mt-1">
                    An indicator appears on their screen first, and the server refuses every frame
                    until their agent confirms it is showing. There is no way to watch somebody
                    without them being able to see that you are. Sessions end themselves after
                    {{ $maxMinutes }} minutes.
                </p>
            </div>

            {{-- Choose who to watch --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-5">
                <h3 class="font-semibold text-gray-800 mb-4">Who do you need to see?</h3>

                @if ($candidates->isEmpty())
                    <p class="text-sm text-gray-500">
                        Nobody has an approved device yet, so there is nothing to watch.
                    </p>
                @else
                    <form method="POST" action="{{ route('monitoring.live.store') }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <div class="flex-1 min-w-64">
                            <x-input-label for="employee_id" value="Employee" />
                            <select id="employee_id" name="employee_id" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="">Choose someone…</option>
                                @foreach ($candidates as $person)
                                    <option value="{{ $person->id }}" @selected($employee?->id === $person->id)>
                                        {{ $person->name }}{{ $person->department ? ' — ' . $person->department : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <x-primary-button :disabled="! $liveEnabled">Request live view</x-primary-button>
                    </form>
                @endif
            </div>

            {{-- The viewer --}}
            @if ($session)
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden"
                     x-data="liveView('{{ route('monitoring.live.frame', $session) }}')"
                     x-init="start()">

                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
                        <div>
                            <span class="font-semibold text-gray-800">{{ $employee?->name }}</span>
                            <span class="block text-xs text-gray-500" x-text="statusLabel()"></span>
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="flex items-center gap-1.5 text-xs font-medium"
                                  :class="streaming ? 'text-green-700' : 'text-amber-600'">
                                <span class="h-2 w-2 rounded-full"
                                      :class="streaming ? 'bg-green-500 animate-pulse' : 'bg-amber-400'"></span>
                                <span x-text="streaming ? 'Streaming' : 'Waiting'"></span>
                            </span>

                            <form method="POST" action="{{ route('monitoring.live.destroy', $session) }}">
                                @csrf
                                @method('DELETE')
                                <button class="px-3 py-1.5 rounded-lg border border-red-200 text-sm text-red-700 hover:bg-red-50">
                                    Stop watching
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="bg-gray-900 aspect-video flex items-center justify-center">
                        <template x-if="frame">
                            <img :src="'data:image/jpeg;base64,' + frame" alt="Live screen" class="w-full h-full object-contain">
                        </template>

                        <template x-if="! frame">
                            <p class="text-sm text-gray-400 px-6 text-center" x-text="waitingMessage()"></p>
                        </template>
                    </div>

                    <div class="px-5 py-3 text-xs text-gray-400 border-t border-gray-100">
                        Frames refresh about twice a second. Nothing is recorded — closing this page
                        leaves no copy of what was on screen.
                    </div>
                </div>
            @endif
        </div>
    </div>

</x-app-layout>
