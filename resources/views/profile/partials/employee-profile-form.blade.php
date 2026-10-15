@php($employee = auth()->user()->employee)

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">{{ __('Employee Information') }}</h2>
        <p class="mt-1 text-sm text-gray-600">
            {{ __('Keep your own work details up to date. Employee Code and Salary are managed by HR.') }}
        </p>
    </header>

    <form method="POST" action="{{ route('profile.employee.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('PATCH')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            {{-- HR-only (read-only): Employee Code --}}
            <div>
                <x-input-label :value="__('Employee Code')" />
                <div class="mt-1 block w-full rounded-md border border-gray-200 bg-gray-100 text-gray-600 px-3 py-2 text-sm">
                    {{ $employee?->employee_code ?? 'Assigned on first save' }}
                </div>
                <p class="mt-1 text-xs text-gray-400">Set by HR</p>
            </div>

            {{-- HR-only (read-only): Salary --}}
            <div>
                <x-input-label :value="__('Salary')" />
                <div class="mt-1 block w-full rounded-md border border-gray-200 bg-gray-100 text-gray-600 px-3 py-2 text-sm">
                    {{ $employee?->salary !== null ? number_format($employee->salary, 2) : '—' }}
                </div>
                <p class="mt-1 text-xs text-gray-400">Set by HR</p>
            </div>

            <div>
                <x-input-label for="designation" :value="__('Designation')" />
                <p class="mt-1 block w-full rounded-md bg-gray-50 border border-gray-200 px-3 py-2 text-sm text-gray-700">{{ $employee?->designation ?: '— set by HR —' }}</p>
                <x-input-error :messages="$errors->get('designation')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="department" :value="__('Department')" />
                <p class="mt-1 block w-full rounded-md bg-gray-50 border border-gray-200 px-3 py-2 text-sm text-gray-700">{{ $employee?->department ?: '— set by HR —' }}</p>
                <x-input-error :messages="$errors->get('department')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="phone" :value="__('Phone Number')" />
                <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full"
                              :value="old('phone', $employee?->phone)" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="hire_date" :value="__('Hiring Date')" />
                <p class="mt-1 block w-full rounded-md bg-gray-50 border border-gray-200 px-3 py-2 text-sm text-gray-700">{{ $employee?->hire_date?->asDate() ?? '— set by HR —' }}</p>
                <x-input-error :messages="$errors->get('hire_date')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="date_of_birth" :value="__('Date of Birth')" />
                <x-text-input id="date_of_birth" name="date_of_birth" type="date" class="mt-1 block w-full"
                              :value="old('date_of_birth', $employee?->date_of_birth?->format('Y-m-d'))" />
                <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
            </div>

            <div class="sm:col-span-2">
                <x-input-label for="address" :value="__('Address')" />
                <textarea id="address" name="address" rows="3"
                          class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('address', $employee?->address) }}</textarea>
                <x-input-error :messages="$errors->get('address')" class="mt-2" />
            </div>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button class="hover:opacity-90" style="background-color:#2f80ed;">{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'employee-profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                   class="text-sm text-gray-600">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
