@php($employee = auth()->user()->employee)

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">{{ __('Employee Information') }}</h2>
        <p class="mt-1 text-sm text-gray-600">
            {{ __('Your HR record. Contact HR if any of these details are incorrect.') }}
        </p>
    </header>

    @if ($employee)
        <dl class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">
            <div>
                <dt class="text-gray-500">Employee Code</dt>
                <dd class="font-medium text-gray-900">{{ $employee->employee_code }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Designation</dt>
                <dd class="font-medium text-gray-900">{{ $employee->designation }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Department</dt>
                <dd class="font-medium text-gray-900">{{ $employee->department }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Phone Number</dt>
                <dd class="font-medium text-gray-900">{{ $employee->phone ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Salary</dt>
                <dd class="font-medium text-gray-900">{{ $employee->salary !== null ? number_format($employee->salary, 2) : '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Hiring Date</dt>
                <dd class="font-medium text-gray-900">{{ $employee->hire_date?->asDate() ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Date of Birth</dt>
                <dd class="font-medium text-gray-900">{{ $employee->date_of_birth?->asDate() ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-gray-500">Address</dt>
                <dd class="font-medium text-gray-900 whitespace-pre-line">{{ $employee->address ?? '—' }}</dd>
            </div>
        </dl>
    @else
        <div class="mt-6 rounded-md bg-yellow-50 p-4 text-sm text-yellow-800">
            You don't have an employee profile yet. Please contact HR to have your record set up.
        </div>
    @endif
</section>
