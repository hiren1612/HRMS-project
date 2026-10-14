<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    {{-- Attendance self-service: available to every logged-in user --}}
                    <x-nav-link :href="route('attendance.index')" :active="request()->routeIs('attendance.index')">
                        {{ __('Attendance') }}
                    </x-nav-link>

                    {{-- Holidays: visible to everyone --}}
                    <x-nav-link :href="route('holidays.index')" :active="request()->routeIs('holidays.*')">
                        {{ __('Holidays') }}
                    </x-nav-link>

                    {{-- Leave self-service: available to every logged-in user except admins --}}
                    @unless (Auth::user()->isAdmin())
                        <x-nav-link :href="route('leaves.index')" :active="request()->routeIs('leaves.index') || request()->routeIs('leaves.create') || request()->routeIs('leaves.show')">
                            {{ __('My Leaves') }}
                        </x-nav-link>
                    @endunless

                    {{-- Leave approvals: managers, HR and admins --}}
                    @if (Auth::user()->hasAnyRole(['admin', 'hr', 'manager']))
                        <x-nav-link :href="route('leaves.manage')" :active="request()->routeIs('leaves.manage')">
                            {{ __('Leave Requests') }}
                        </x-nav-link>
                    @endif

                    {{-- HR link: admin + hr only --}}
                    @if (Auth::user()->hasAnyRole(['admin', 'hr']))
                        <x-nav-link :href="route('hr.dashboard')" :active="request()->routeIs('hr.dashboard')">
                            {{ __('HR') }}
                        </x-nav-link>
                        <x-nav-link :href="route('employees.index')" :active="request()->routeIs('employees.*')">
                            {{ __('Employees') }}
                        </x-nav-link>
                        <x-nav-link :href="route('hrms.dashboard')" :active="request()->routeIs('hrms.dashboard')">
                            {{ __('HRMS Dashboard') }}
                        </x-nav-link>
                    @endif

                    {{-- Admin link: admin only --}}
                    @if (Auth::user()->isAdmin())
                        <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                            {{ __('Admin') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Notifications Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                @php($unreadNotifications = Auth::user()->unreadNotifications)
                <x-dropdown align="right" width="w-80">
                    <x-slot name="trigger">
                        <button class="relative inline-flex items-center p-2 text-gray-500 hover:text-gray-700 focus:outline-none">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            @if ($unreadNotifications->count() > 0)
                                <span class="absolute top-0 right-0 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-600 rounded-full">
                                    {{ $unreadNotifications->count() }}
                                </span>
                            @endif
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="px-4 py-2 flex items-center justify-between border-b">
                            <span class="text-sm font-semibold text-gray-700">Notifications</span>
                            @if ($unreadNotifications->count() > 0)
                                <form method="POST" action="{{ route('notifications.readAll') }}">
                                    @csrf
                                    <button type="submit" class="text-xs text-indigo-600 hover:underline">Mark all read</button>
                                </form>
                            @endif
                        </div>

                        @forelse ($unreadNotifications->take(5) as $notification)
                            <a href="{{ route('notifications.index') }}"
                               class="block px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 border-b border-gray-100">
                                <div>{{ $notification->data['message'] ?? 'Notification' }}</div>
                                <div class="text-xs text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</div>
                            </a>
                        @empty
                            <div class="px-4 py-4 text-sm text-gray-500 text-center">No new notifications</div>
                        @endforelse

                        <a href="{{ route('notifications.index') }}"
                           class="block px-4 py-2 text-center text-xs text-indigo-600 hover:bg-gray-50">
                            View all notifications
                        </a>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('attendance.index')" :active="request()->routeIs('attendance.index')">
                {{ __('Attendance') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('holidays.index')" :active="request()->routeIs('holidays.*')">
                {{ __('Holidays') }}
            </x-responsive-nav-link>

            @unless (Auth::user()->isAdmin())
                <x-responsive-nav-link :href="route('leaves.index')" :active="request()->routeIs('leaves.index')">
                    {{ __('My Leaves') }}
                </x-responsive-nav-link>
            @endunless

            @if (Auth::user()->hasAnyRole(['admin', 'hr', 'manager']))
                <x-responsive-nav-link :href="route('leaves.manage')" :active="request()->routeIs('leaves.manage')">
                    {{ __('Leave Requests') }}
                </x-responsive-nav-link>
            @endif

            @if (Auth::user()->hasAnyRole(['admin', 'hr']))
                <x-responsive-nav-link :href="route('hr.dashboard')" :active="request()->routeIs('hr.dashboard')">
                    {{ __('HR') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('employees.index')" :active="request()->routeIs('employees.*')">
                    {{ __('Employees') }}
                </x-responsive-nav-link>
            @endif

            @if (Auth::user()->isAdmin())
                <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                    {{ __('Admin') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
