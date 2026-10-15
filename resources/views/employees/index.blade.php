@section('title', 'Employees')

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Employees') }}</h2>
            <a href="{{ route('employees.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-xs font-semibold uppercase rounded-md hover:bg-gray-700">
                + New Employee
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Flash success message (set by the controller via ->with('success', ...)) --}}
            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Search and filters, above the table --}}
            <form method="GET" class="bg-white shadow-sm sm:rounded-lg p-4 mb-4"
                  x-data="{ t: null }"
                  x-on:input.debounce.400ms="clearTimeout(t); $el.requestSubmit()">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="lg:col-span-2">
                        <label for="emp-search" class="sr-only">Search employees</label>
                        <x-text-input id="emp-search" name="search" type="search" class="block w-full"
                                      :value="request('search')"
                                      placeholder="Search name, email, code, designation, department or phone" />
                    </div>

                    <label for="emp-dept" class="sr-only">Filter by department</label>
                    <select id="emp-dept" name="department" aria-label="Filter by department" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department }}" @selected(request('department') === $department)>{{ $department }}</option>
                        @endforeach
                    </select>

                    <label for="emp-status" class="sr-only">Filter by status</label>
                    <select id="emp-status" name="status" aria-label="Filter by status" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                        <option value="">Any status</option>
                        @foreach (\App\Models\Employee::STATUSES as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mt-3 flex items-center gap-3">
                    <x-primary-button>Search</x-primary-button>
                    @if (request()->hasAny(['search', 'department', 'status']))
                        <a href="{{ route('employees.index') }}" class="text-sm text-gray-600 hover:underline">Clear</a>
                    @endif
                    <span class="ml-auto text-sm text-gray-500">
                        {{ $employees->total() }} {{ Str::plural('employee', $employees->total()) }}
                    </span>
                </div>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-y-hidden whitespace-nowrap overflow-x-auto w-100">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Code</th>
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Designation</th>
                            <th class="px-6 py-3">Department</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse ($employees as $employee)
                            <tr>
                                <td class="px-6 py-4 font-medium">{{ $employee->employee_code }}</td>
                                <td class="px-6 py-4">{{ $employee->displayName() }}</td>
                                <td class="px-6 py-4">{{ $employee->designation }}</td>
                                <td class="px-6 py-4">{{ $employee->department }}</td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold
                                        {{ $employee->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                                        {{ ucfirst($employee->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
                                    <a href="{{ route('employees.show', $employee) }}" class="text-indigo-600 hover:underline">View</a>
                                    <a href="{{ route('employees.edit', $employee) }}" class="text-blue-600 hover:underline">Edit</a>
                                    <form action="{{ route('employees.destroy', $employee) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Delete this employee?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    @if (request()->hasAny(['search', 'department', 'status']))
                                        No employees match your search.
                                    @else
                                        No employees yet. Click “New Employee” to add one.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination links --}}
            <div class="mt-4">
                {{ $employees->links() }}
            </div>

        </div>
    </div>
</x-app-layout>
