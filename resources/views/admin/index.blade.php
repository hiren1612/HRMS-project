@section('title', 'Admin')

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Admin — User & Role Management') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            {{-- Role stats --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <div class="text-xs text-gray-500 uppercase">Total Users</div>
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['users'] }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border-l-4 border-red-400">
                    <div class="text-xs text-gray-500 uppercase">Admins</div>
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['admins'] }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border-l-4 border-indigo-400">
                    <div class="text-xs text-gray-500 uppercase">HR</div>
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['hr'] }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border-l-4 border-blue-400">
                    <div class="text-xs text-gray-500 uppercase">Managers</div>
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['managers'] }}</div>
                </div>
            </div>

            {{-- Users table --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <div class="px-6 py-4 border-b flex items-center justify-between gap-3">
                    <h3 class="font-semibold text-gray-800">All Users</h3>
                    {{-- QA M14: TimeCamp sync withdrawn. Time tracking is handled by the
     separate desktop tracker, so this button pointed at an unused
     integration. The service and command remain for reference. --}}
                </div>
                <table class="rt min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Email</th>
                            <th class="px-6 py-3">Employee Profile</th>
                            <th class="px-6 py-3">Role</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse ($users as $user)
                            <tr>
                                <td data-label="Name" class="px-6 py-4 font-medium">
                                    {{ $user->name }}
                                    @if ($user->is(auth()->user()))
                                        <span class="ml-1 text-xs text-gray-400">(you)</span>
                                    @endif
                                </td>
                                <td data-label="Email" class="px-6 py-4">{{ $user->email }}</td>
                                <td data-label="Employee Profile" class="px-6 py-4">
                                    @if ($user->employee)
                                        <span class="text-green-700">{{ $user->employee->employee_code }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td data-label="Role" class="px-6 py-4">
                                    @if ($user->is(auth()->user()))
                                        {{-- Can't change your own role --}}
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold uppercase bg-red-100 text-red-800">
                                            {{ $user->role }}
                                        </span>
                                        <span class="ml-1 text-xs text-gray-400">locked</span>
                                    @else
                                        <form method="POST" action="{{ route('admin.users.role', $user) }}" class="flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <select name="role"
                                                    class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                                @foreach (\App\Models\User::ROLES as $role)
                                                    <option value="{{ $role }}" @selected($user->role === $role)>{{ ucfirst($role) }}</option>
                                                @endforeach
                                            </select>
                                            <button class="px-3 py-1 bg-gray-800 text-white text-xs rounded hover:bg-gray-700">Save</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">No users found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $users->links() }}</div>
        </div>
    </div>
</x-app-layout>
