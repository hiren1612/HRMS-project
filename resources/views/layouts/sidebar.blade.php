@php($u = Auth::user())

<aside
    class="fixed inset-y-0 left-0 z-30 w-64 bg-white border-r border-gray-200 overflow-y-auto transform transition-transform duration-200 lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">

    {{-- Brand --}}
    <div class="h-16 flex items-center gap-2 px-5 border-b border-gray-100">
        <img src="{{ asset('images/favicon.png') }}" alt="" class="h-9 w-9 rounded-lg object-contain">
        <span class="text-lg font-bold text-gray-800">{{ config('app.name', 'HRMS') }}</span>
    </div>

    {{-- Logged-in user mini-card --}}
    <div class="px-5 py-4 flex items-center gap-3 border-b border-gray-100">
        @if ($u->profilePhotoUrl())
            <img src="{{ $u->profilePhotoUrl() }}" alt="{{ $u->name }}" class="h-10 w-10 rounded-full object-cover">
        @else
            <span class="h-10 w-10 rounded-full flex items-center justify-center text-white text-sm font-bold" style="background-color:#2f80ed;">{{ $u->initial() }}</span>
        @endif
        <div class="min-w-0">
            <div class="text-sm font-medium text-gray-800 truncate">{{ $u->name }}</div>
            <div class="text-xs text-gray-400 uppercase">{{ $u->role }}</div>
        </div>
    </div>

    <nav class="px-3 py-5 space-y-6">

        {{-- GENERAL --}}
        <div>
            <p class="px-3 mb-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">General</p>

            <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Dashboard
            </x-sidebar-link>

            <x-sidebar-link :href="route('attendance.index')" :active="request()->routeIs('attendance.*')">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Attendance
            </x-sidebar-link>

            @unless ($u->isAdmin())
                <x-sidebar-link :href="route('leaves.index')" :active="request()->routeIs('leaves.index') || request()->routeIs('leaves.create') || request()->routeIs('leaves.show')">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    My Leaves
                </x-sidebar-link>
            @endunless

            <x-sidebar-link :href="route('holidays.index')" :active="request()->routeIs('holidays.*')">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Holidays
            </x-sidebar-link>
            <x-sidebar-link :href="route('activity.index')" :active="request()->routeIs('activity.*')">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Activity Log
            </x-sidebar-link>
        </div>

        {{-- MANAGEMENT --}}
        @if ($u->hasAnyRole(['admin', 'hr', 'manager']))
            <div>
                <p class="px-3 mb-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Management</p>

                <x-sidebar-link :href="route('leaves.manage')" :active="request()->routeIs('leaves.manage')">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Leave Requests
                </x-sidebar-link>

                @if ($u->hasAnyRole(['admin', 'hr']))
                    <x-sidebar-link :href="route('employees.index')" :active="request()->routeIs('employees.*')">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-2a4 4 0 100-8 4 4 0 000 8z"/></svg>
                        Employees
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('hrms.dashboard')" :active="request()->routeIs('hrms.dashboard')">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        HRMS Stats
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('goals.index')" :active="request()->routeIs('goals.*')">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8a4 4 0 100 8 4 4 0 000-8zm0-5a9 9 0 100 18 9 9 0 000-18zm0 8a1 1 0 100 2 1 1 0 000-2z"/></svg>
                        Goals
                    </x-sidebar-link>

                    <x-sidebar-link :href="route('schedules.index')" :active="request()->routeIs('schedules.*')">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Schedules
                    </x-sidebar-link>
                @endif

                @if ($u->isAdmin())
                    <x-sidebar-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Admin
                    </x-sidebar-link>
                @endif
            </div>
        @endif

        {{-- ACCOUNT --}}
        <div>
            <p class="px-3 mb-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Account</p>

            <x-sidebar-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                My Profile
            </x-sidebar-link>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    Log Out
                </button>
            </form>
        </div>
    </nav>
</aside>
