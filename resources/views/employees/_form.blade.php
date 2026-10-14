{{--
    Shared create/edit form.
    Expects:  $users (selectable users), and optionally $employee (when editing).
    The parent view sets the <form> action/method and includes this partial.
--}}
@php($employee = $employee ?? null)

@csrf

{{-- Validation summary --}}
@if ($errors->any())
    <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
        <ul class="list-disc list-inside space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-2 gap-6">

    {{-- Employee name --}}
    <div>
        <x-input-label for="name" :value="__('Employee name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                      :value="old('name', $employee?->name ?? $employee?->user?->name)"
                      required maxlength="150" autofocus />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
        @if ($employee?->user)
            <p class="mt-1 text-xs text-gray-500">Linked login: {{ $employee->user->email }}</p>
        @endif
    </div>

    {{-- Employee code --}}
    <div>
        <x-input-label for="employee_code" :value="__('Employee code')" />
        <x-text-input id="employee_code" name="employee_code" type="text" class="mt-1 block w-full"
                      :value="old('employee_code', $employee?->employee_code)" placeholder="EMP-0001" />
        <x-input-error :messages="$errors->get('employee_code')" class="mt-2" />
    </div>

    {{-- Designation --}}
    <div>
        <x-input-label for="designation" :value="__('Designation')" />
        <x-text-input id="designation" name="designation" type="text" class="mt-1 block w-full"
                      :value="old('designation', $employee?->designation)" />
        <x-input-error :messages="$errors->get('designation')" class="mt-2" />
    </div>

    {{-- Department --}}
    <div>
        <x-input-label for="department" :value="__('Department')" />
        <x-text-input id="department" name="department" type="text" class="mt-1 block w-full"
                      :value="old('department', $employee?->department)" />
        <x-input-error :messages="$errors->get('department')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="manager_id" :value="__('Reports To')" />
        <select id="manager_id" name="manager_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            <option value="">- No line manager -</option>
            @foreach ($managers as $id => $label)
                <option value="{{ $id }}" @selected(old('manager_id', $employee?->manager_id) == $id)>{{ $label }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('manager_id')" class="mt-2" />
    </div>

    {{-- Phone --}}
    <div>
        <x-input-label for="phone" :value="__('Phone')" />
        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full"
                      :value="old('phone', $employee?->phone)" />
        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
    </div>

    {{-- Address (full width) --}}
    <div class="sm:col-span-2">
        <x-input-label for="address" :value="__('Address')" />
        <textarea id="address" name="address" rows="3"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('address', $employee?->address) }}</textarea>
        <x-input-error :messages="$errors->get('address')" class="mt-2" />
    </div>

    {{-- Hire date --}}
    <div>
        <x-input-label for="hire_date" :value="__('Hire date')" />
        <x-text-input id="hire_date" name="hire_date" type="date" class="mt-1 block w-full"
                      :value="old('hire_date', $employee?->hire_date?->format('Y-m-d'))" />
        <x-input-error :messages="$errors->get('hire_date')" class="mt-2" />
    </div>

    {{-- Date of birth (for birthday wishes) --}}
    <div>
        <x-input-label for="date_of_birth" :value="__('Date of Birth')" />
        <x-text-input id="date_of_birth" name="date_of_birth" type="date" class="mt-1 block w-full"
                      :value="old('date_of_birth', $employee?->date_of_birth?->format('Y-m-d'))" />
        <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
    </div>

    {{-- Status --}}
    <div>
        <x-input-label for="status" :value="__('Status')" />
        <select name="status" id="status"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
            @foreach (\App\Models\Employee::STATUSES as $status)
                <option value="{{ $status }}"
                    @selected(old('status', $employee?->status ?? 'active') === $status)>
                    {{ ucfirst($status) }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('status')" class="mt-2" />
    </div>

</div>

<div class="flex items-center gap-4 mt-6">
    <x-primary-button>{{ $submitLabel ?? __('Save') }}</x-primary-button>
    <a href="{{ route('employees.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
</div>
