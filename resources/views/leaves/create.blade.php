<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Apply for Leave') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                @if ($errors->any())
                    <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('leaves.store') }}"
                      x-data="{ multiple: {{ old('multiple_days') ? 'true' : 'false' }} }" class="space-y-6"
                      x-data="{
                          start: '', end: '', multi: false,
                          holidays: {{ Js::from($nonWorkingDates) }},
                          get days() {
                              if (!this.start) return 0;
                              const a = new Date(this.start);
                              const b = new Date(this.multi && this.end ? this.end : this.start);
                              if (isNaN(a) || isNaN(b) || b < a) return 0;
                              let n = 0;
                              for (let d = new Date(a); d <= b; d.setDate(d.getDate() + 1)) {
                                  const iso = d.toISOString().slice(0, 10);
                                  const wd = d.getDay();
                                  if (wd !== 0 && wd !== 6 && !this.holidays.includes(iso)) n++;
                              }
                              return n;
                          }
                      }">
                    @csrf

                    {{-- Apply for multiple days --}}
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="multiple_days" value="1" x-model="multiple"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">{{ __('Apply for multiple days') }}</span>
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        {{-- Date / Start date --}}
                        <div>
                            <x-input-label for="start_date">
                                <span x-text="multiple ? 'Start date' : 'Date'">Date</span> <span class="text-red-500">*</span>
                            </x-input-label>
                            <x-text-input id="start_date" name="start_date" type="date" x-model="start" class="mt-1 block w-full"
                                          :min="$earliest" :value="old('start_date')" required />
                            <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                        </div>

                        {{-- Select leave session --}}
                        <div>
                            <x-input-label for="session">{{ __('Select leave session') }} <span class="text-red-500">*</span></x-input-label>
                            <select name="session" id="session"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach (\App\Models\Leave::SESSIONS as $value => $label)
                                    <option value="{{ $value }}" @selected(old('session', 'full_day') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('session')" class="mt-2" />
                        </div>

                        {{-- End date (only when applying for multiple days) --}}
                        <div x-show="multiple" style="display:none;">
                            <x-input-label for="end_date">{{ __('End date') }} <span class="text-red-500">*</span></x-input-label>
                            <x-text-input id="end_date" name="end_date" type="date" x-model="end" class="mt-1 block w-full"
                                          :min="$earliest" :value="old('end_date')" />
                            <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
                        </div>
                    </div>

                    {{-- Select leave type --}}
                    <div>
                        <x-input-label for="leave_type">{{ __('Select leave type') }} <span class="text-red-500">*</span></x-input-label>
                        <select name="leave_type" id="leave_type"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">— Select —</option>
                            @foreach (\App\Models\Leave::TYPES as $value => $label)
                                <option value="{{ $value }}" @selected(old('leave_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('leave_type')" class="mt-2" />
                    </div>

                    {{-- Leave reason --}}
                    <div>
                        <x-input-label for="reason">{{ __('Leave reason') }} <span class="text-red-500">*</span></x-input-label>
                        <textarea id="reason" name="reason" rows="3" required
                                  class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('reason') }}</textarea>
                        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center justify-center gap-4 pt-2">
                        <x-primary-button>{{ __('Apply') }}</x-primary-button>
                        <a href="{{ route('leaves.index') }}"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                            {{ __('Cancel') }}
                        </a>
                    </div>
                
    {{-- QA U3: what this request will actually cost, before Apply --}}
    <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4" x-show="days > 0" x-cloak>
        <p class="text-sm text-gray-800">
            This uses <span class="font-semibold" x-text="days"></span>
            <span x-text="days === 1 ? 'working day' : 'working days'"></span>
            <span class="text-gray-500">(weekends and public holidays are not counted)</span>
        </p>
        @foreach ($balances as $balance)
            @if (! ($balance['carry'] ?? false))
                <p class="mt-1 text-xs text-gray-600">
                    {{ $balance['label'] }}: <span class="font-semibold">{{ $balance['remaining'] }}</span> of {{ $balance['allowance'] }} left
                </p>
            @endif
        @endforeach
    </div>

                </form>

            </div>
        </div>
    </div>
</x-app-layout>
