<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Employee Details') }}</h2>
            <a href="{{ route('employees.edit', $employee) }}"
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-xs font-semibold uppercase rounded-md hover:bg-blue-500">
                Edit
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Employee Code</dt>
                        <dd class="font-medium text-gray-900">{{ $employee->employee_code }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Name</dt>
                        <dd class="font-medium text-gray-900">{{ $employee->displayName() }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Email</dt>
                        <dd class="font-medium text-gray-900">{{ $employee->user->email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Phone</dt>
                        <dd class="font-medium text-gray-900">{{ $employee->phone ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Address</dt>
                        <dd class="font-medium text-gray-900 whitespace-pre-line">{{ $employee->address ?? '—' }}</dd>
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
                        <dt class="text-gray-500">Salary</dt>
                        <dd class="font-medium text-gray-900">{{ $employee->salary !== null ? number_format($employee->salary, 2) : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Hire Date</dt>
                        <dd class="font-medium text-gray-900">{{ $employee->hire_date?->asDate() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Date of Birth</dt>
                        <dd class="font-medium text-gray-900">{{ $employee->date_of_birth?->asDate() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Status</dt>
                        <dd>
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold
                                {{ $employee->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                                {{ ucfirst($employee->status) }}
                            </span>
                        </dd>
                    </div>
                </dl>

                <div class="mt-6">
                    <a href="{{ route('employees.index') }}" class="text-sm text-gray-600 hover:underline">&larr; Back to list</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
