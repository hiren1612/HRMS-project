@section('title', 'Holidays')

<x-app-layout>
    <x-slot name="header">
        {{-- QA U4: on a phone the title, country picker and two buttons used to
             sit on one line and run off the screen. They now wrap, and the
             import form takes the full width on the smallest screens. --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Company Holidays') }}</h2>
            @if (auth()->user()->hasAnyRole(['admin', 'hr']))
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    {{-- Import festivals from Google's public holiday calendar --}}
                    <form method="POST" action="{{ route('holidays.importGoogle') }}"
                          class="flex flex-1 sm:flex-none items-center gap-2"
                          x-data="{ busy: false }" x-on:submit="busy = true">
                        @csrf
                        <label for="country" class="sr-only">Country to import holidays for</label>
                        <select id="country" name="country"
                                class="min-w-0 flex-1 sm:flex-none text-xs border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-2">
                            <option value="in">🇮🇳 India</option>
                            <option value="us">🇺🇸 United States</option>
                            <option value="uk">🇬🇧 United Kingdom</option>
                            <option value="ca">🇨🇦 Canada</option>
                            <option value="au">🇦🇺 Australia</option>
                            <option value="sg">🇸🇬 Singapore</option>
                            <option value="ae">🇦🇪 UAE</option>
                            <option value="ph">🇵🇭 Philippines</option>
                            <option value="za">🇿🇦 South Africa</option>
                        </select>
                        {{-- Secondary: importing is a helper, adding a holiday is the main action. --}}
                        <x-page-action tone="secondary" x-bind:disabled="busy" class="whitespace-nowrap">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span x-text="busy ? 'Importing…' : 'Import'"></span>
                        </x-page-action>
                    </form>

                    <x-page-action :href="route('holidays.create')" class="whitespace-nowrap">+ Add Holiday</x-page-action>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            @php($canManage = auth()->user()->hasAnyRole(['admin', 'hr']))

            {{-- ============ MONTHLY CALENDAR ============ --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                {{-- Month navigation --}}
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">{{ $cursor->format('F Y') }}</h3>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('holidays.index', ['month' => $cursor->copy()->subMonth()->format('Y-m')]) }}"
                           class="px-3 py-1.5 text-sm rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50">&larr; Prev</a>
                        <a href="{{ route('holidays.index', ['month' => now()->format('Y-m')]) }}"
                           class="px-3 py-1.5 text-sm rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50">Today</a>
                        <a href="{{ route('holidays.index', ['month' => $cursor->copy()->addMonth()->format('Y-m')]) }}"
                           class="px-3 py-1.5 text-sm rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50">Next &rarr;</a>
                    </div>
                </div>

                {{-- Weekday headers. Three letters is as much as fits on a phone,
                     so the full name is announced to screen readers instead. --}}
                <div class="grid grid-cols-7 text-center text-xs font-semibold text-gray-500 uppercase border-b border-gray-200 pb-2">
                    @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $weekday)
                        <div>
                            <span aria-hidden="true">{{ Str::substr($weekday, 0, 3) }}</span>
                            <span class="sr-only">{{ $weekday }}</span>
                        </div>
                    @endforeach
                </div>

                {{-- Day cells.
                     QA U12: the holiday name was 11px and truncated to a few
                     characters, so it could not be read. It now wraps at the
                     normal small size and the cells are tall enough to hold it.
                     QA U4: on a phone a seven-column grid leaves roughly 45px
                     per day, so below sm the month becomes a list of the days
                     that actually have something on them. --}}
                <div class="hidden sm:grid grid-cols-7">
                    @foreach ($calendar as $cell)
                        <div class="min-h-[112px] border border-gray-100 p-2 {{ $cell['cellClass'] }}">
                            {{-- Date number --}}
                            <div class="flex justify-end">
                                <span class="flex items-center justify-center h-7 w-7 text-sm {{ $cell['numClass'] }}">
                                    {{ $cell['number'] }}
                                </span>
                            </div>

                            {{-- Holiday chips for this day --}}
                            @if ($cell['holidays']->isNotEmpty())
                                <div class="mt-1 space-y-1">
                                    @foreach ($cell['holidays'] as $holiday)
                                        <div class="rounded bg-green-100 px-1.5 py-1 text-xs font-medium leading-snug text-green-800 break-words">
                                            {{ $holiday->title }}
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Phone view of the same month. --}}
                <div class="sm:hidden divide-y divide-gray-100">
                    @forelse ($holidays as $holiday)
                        <div class="flex items-start gap-3 py-3">
                            <div class="flex-none w-14 text-center">
                                <div class="text-lg font-bold text-gray-800 leading-none">{{ $holiday->date->format('d') }}</div>
                                <div class="text-xs text-gray-500">{{ $holiday->date->format('D') }}</div>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-gray-900">{{ $holiday->title }}</div>
                                @if ($holiday->description)
                                    <div class="text-xs text-gray-600">{{ $holiday->description }}</div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-gray-500">Nothing in {{ $cursor->format('F Y') }}.</p>
                    @endforelse
                </div>

                <div class="mt-3 flex items-center gap-4 text-xs text-gray-500">
                    <span class="inline-flex items-center gap-1">
                        <span class="inline-block h-4 w-4 rounded-full bg-indigo-600"></span> Today
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <span class="inline-block h-3 w-3 rounded bg-green-100 border border-green-300"></span> Holiday
                    </span>
                </div>
            </div>

            {{-- ============ HOLIDAY LIST ============ --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <div class="px-6 py-4 border-b">
                    <h3 class="font-semibold text-gray-800">Holidays in {{ $cursor->format('F Y') }}</h3>
                </div>
                <table class="rt min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Date</th>
                            <th class="px-6 py-3">Day</th>
                            <th class="px-6 py-3">Holiday</th>
                            <th class="px-6 py-3">Description</th>
                            @if ($canManage)
                                <th class="px-6 py-3 text-right">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse ($holidays as $holiday)
                            <tr class="{{ $holiday->isUpcoming() ? '' : 'opacity-60' }}">
                                <td data-label="Date" class="px-6 py-4 font-medium whitespace-nowrap">
                                    {{ $holiday->date->asDate() }}
                                    @if ($holiday->isUpcoming())
                                        <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">Upcoming</span>
                                    @endif
                                </td>
                                <td data-label="Day" class="px-6 py-4">{{ $holiday->date->format('l') }}</td>
                                <td data-label="Holiday" class="px-6 py-4 font-medium text-gray-900">{{ $holiday->title }}</td>
                                <td data-label="Description" class="px-6 py-4 text-gray-600">{{ $holiday->description ?? '—' }}</td>
                                @if ($canManage)
                                    <td data-label="" class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
                                        <a href="{{ route('holidays.edit', $holiday) }}" class="text-blue-600 hover:underline">Edit</a>
                                        <x-confirm-action :action="route('holidays.destroy', $holiday)"
                                                          title="Delete this holiday?"
                                                          :message="$holiday->title . ' on ' . $holiday->date->asDate() . ' will stop counting as a holiday, so leave taken across that day will use an extra day.'" />
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManage ? 5 : 4 }}" class="px-6 py-8 text-center text-gray-500">
                                    No holidays in {{ $cursor->format('F Y') }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
