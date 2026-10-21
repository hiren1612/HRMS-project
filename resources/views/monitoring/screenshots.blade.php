@section('title', 'Screenshots')

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Screenshots') }}</h2>
            <a href="{{ route('monitoring.overview') }}" class="text-sm text-indigo-600 hover:underline">Overview →</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            {{-- Filters --}}
            <form method="GET" class="bg-white shadow-sm sm:rounded-lg p-4 mb-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label for="shot-employee" class="sr-only">Employee</label>
                        <select id="shot-employee" name="employee"
                                class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">Everyone with screenshots</option>
                            @foreach ($employees as $person)
                                <option value="{{ $person->id }}" @selected($employee?->id === $person->id)>
                                    {{ $person->name }}{{ $person->employee_code ? ' — ' . $person->employee_code : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="shot-day" class="sr-only">Day</label>
                        <input id="shot-day" type="date" name="day" value="{{ $day->toDateString() }}"
                               max="{{ now()->toDateString() }}"
                               class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                    </div>
                </div>

                <div class="mt-3 flex items-center gap-3 flex-wrap">
                    <x-primary-button>Show</x-primary-button>
                    @if (request()->hasAny(['employee', 'day']))
                        <a href="{{ route('monitoring.screenshots') }}" class="text-sm text-gray-600 hover:underline">Clear</a>
                    @endif
                    <span class="ml-auto text-sm text-gray-500">
                        {{ $screenshots->total() }} {{ Str::plural('screenshot', $screenshots->total()) }}
                    </span>
                </div>
            </form>

            @if ($screenshots->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-10 text-center">
                    <p class="text-gray-500">
                        No screenshots for {{ $employee?->name ?? 'anyone' }} on
                        {{ $day->isoFormat('dddd, D MMMM YYYY') }}.
                    </p>
                    <p class="mt-2 text-sm text-gray-400">
                        Screenshots are only captured while tracking is on, the employee is active,
                        and the foreground window is not on the exclusion list.
                    </p>
                </div>
            @else
                {{-- Grid. Each tile opens the full image in a dialog rather
                     than a new tab, so leaving the gallery does not mean
                     losing the day you were looking at. --}}
                <div x-data="{ open: false, src: '', meta: '' }">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                        @foreach ($screenshots as $shot)
                            <figure class="bg-white shadow-sm rounded-lg overflow-hidden">
                                <button type="button" class="block w-full text-left"
                                        x-on:click="open = true; src = '{{ route('monitoring.screenshots.show', $shot) }}'; meta = '{{ $shot->employee?->name }} — {{ $shot->captured_at?->asDateTime() }}'">
                                    <img src="{{ route('monitoring.screenshots.show', $shot) }}"
                                         alt="Screen of {{ $shot->employee?->name ?? 'an employee' }} at {{ $shot->captured_at?->asTime() }}"
                                         loading="lazy"
                                         class="w-full aspect-video object-cover bg-gray-100">
                                </button>

                                <figcaption class="p-3">
                                    <span class="block text-sm font-medium text-gray-800 truncate">
                                        {{ $shot->employee?->name ?? 'Unknown' }}
                                    </span>
                                    <span class="block text-xs text-gray-400">
                                        {{ $shot->captured_at?->asTime() }}
                                        @if ($shot->status_at_capture !== 'active')
                                            · <span class="text-amber-600">{{ $shot->status_at_capture }}</span>
                                        @endif
                                    </span>
                                    <span class="block text-xs text-gray-400 truncate">
                                        {{ $shot->device?->device_name }} · {{ $shot->sizeLabel() }}
                                    </span>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>

                    {{-- Full-size viewer --}}
                    <div x-show="open" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
                         x-on:click.self="open = false" x-on:keydown.escape.window="open = false">
                        <div class="max-w-6xl w-full">
                            <div class="flex items-center justify-between mb-2 text-white text-sm">
                                <span x-text="meta"></span>
                                <button type="button" x-on:click="open = false" class="hover:underline">Close (Esc)</button>
                            </div>
                            <img :src="src" alt="" class="w-full rounded-lg bg-black">
                        </div>
                    </div>
                </div>

                <div class="mt-6">{{ $screenshots->links() }}</div>
            @endif

            <p class="mt-6 text-xs text-gray-400">
                Every image opened is written to the audit log with who opened it. Screenshots are
                deleted automatically after {{ $retentionDays }} {{ Str::plural('day', $retentionDays) }}.
            </p>
        </div>
    </div>
</x-app-layout>
