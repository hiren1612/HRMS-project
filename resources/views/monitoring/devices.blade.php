@section('title', 'Tracking Devices')

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Tracking Devices') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            {{-- Status tabs --}}
            <div class="mb-4 flex flex-wrap gap-2">
                @foreach (['pending' => 'Awaiting approval', 'approved' => 'Approved', 'revoked' => 'Revoked'] as $key => $label)
                    <a href="{{ route('monitoring.devices', ['status' => $key]) }}"
                       class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $status === $key ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50' }}">
                        {{ $label }}
                        <span class="ml-1 text-xs {{ $key === 'pending' && $counts['pending'] > 0 ? 'text-amber-600 font-semibold' : 'text-gray-400' }}">
                            {{ $counts[$key] }}
                        </span>
                    </a>
                @endforeach
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="rt min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-600">Employee</th>
                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-600">Device</th>
                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-600">Last seen</th>
                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold text-gray-600">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($devices as $device)
                                <tr class="hover:bg-gray-50">
                                    <td data-label="Employee" class="px-4 py-3">
                                        <span class="font-medium text-gray-800">{{ $device->employee?->name ?? 'Unknown' }}</span>
                                        @if ($device->employee?->employee_code)
                                            <span class="block text-xs text-gray-400">{{ $device->employee->employee_code }}</span>
                                        @endif
                                    </td>

                                    <td data-label="Device" class="px-4 py-3">
                                        <span class="text-gray-800">{{ $device->device_name }}</span>
                                        <span class="block text-xs text-gray-400">
                                            {{ $device->platform ?: 'Unknown platform' }}
                                            @if ($device->agent_version) · agent {{ $device->agent_version }} @endif
                                        </span>
                                    </td>

                                    <td data-label="Last seen" class="px-4 py-3 text-gray-600">
                                        {{ $device->last_seen_at?->diffForHumans() ?? 'never' }}
                                        @if ($device->last_ip)
                                            <span class="block text-xs text-gray-400">{{ $device->last_ip }}</span>
                                        @endif
                                    </td>

                                    <td data-label="Status" class="px-4 py-3">
                                        @if ($device->status === 'approved')
                                            <span class="text-xs font-medium text-green-700">Approved</span>
                                            @if ($device->approver)
                                                <span class="block text-xs text-gray-400">
                                                    by {{ $device->approver->name }}{{ $device->approved_at ? ', ' . $device->approved_at->asDate() : '' }}
                                                </span>
                                            @endif
                                        @elseif ($device->status === 'pending')
                                            <span class="text-xs font-medium text-amber-600">Awaiting approval</span>
                                        @else
                                            <span class="text-xs font-medium text-gray-500">Revoked</span>
                                        @endif
                                    </td>

                                    <td data-label="Action" class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            @if ($device->status !== 'approved')
                                                <form method="POST" action="{{ route('monitoring.devices.approve', $device) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="text-sm font-medium text-indigo-600 hover:underline">Approve</button>
                                                </form>
                                            @endif

                                            @if ($device->status !== 'revoked')
                                                {{-- A revoke is hard to undo quietly: it destroys the
                                                     device's token, so the employee must sign in again.
                                                     Worth a confirmation step rather than a bare button. --}}
                                                <div x-data="{ open: false }" class="inline">
                                                    <button type="button" x-on:click="open = true"
                                                            class="text-sm font-medium text-red-600 hover:underline">Revoke</button>

                                                    <div x-show="open" x-cloak
                                                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                                                         x-on:click.self="open = false" x-on:keydown.escape.window="open = false">
                                                        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 text-left">
                                                            <h3 class="font-semibold text-gray-800">
                                                                Revoke {{ $device->device_name }}?
                                                            </h3>
                                                            <p class="mt-2 text-sm text-gray-600">
                                                                Its sign-in token is destroyed immediately and any open
                                                                session is closed. {{ $device->employee?->name ?? 'The employee' }}
                                                                will have to sign in again on that machine.
                                                            </p>

                                                            <form method="POST" action="{{ route('monitoring.devices.revoke', $device) }}" class="mt-4">
                                                                @csrf
                                                                @method('PATCH')
                                                                <label for="reason-{{ $device->id }}" class="block text-sm text-gray-700">Reason (optional)</label>
                                                                <input id="reason-{{ $device->id }}" name="reason" type="text" maxlength="200"
                                                                       placeholder="Laptop returned, left the company…"
                                                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">

                                                                <div class="mt-5 flex justify-end gap-3">
                                                                    <button type="button" x-on:click="open = false"
                                                                            class="text-sm text-gray-600 hover:underline">Cancel</button>
                                                                    <button class="px-4 py-2 rounded-md bg-red-600 text-white text-sm font-medium hover:bg-red-700">
                                                                        Revoke device
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-gray-500">
                                        @if ($status === 'pending')
                                            Nothing waiting for approval.
                                        @else
                                            No {{ $status }} devices.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($devices->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100">{{ $devices->links() }}</div>
                @endif
            </div>

            <p class="mt-4 text-xs text-gray-400">
                A device that is not approved can read its settings and nothing else — no activity,
                no screenshots. Approvals and revocations are both written to the audit log.
            </p>
        </div>
    </div>
</x-app-layout>
