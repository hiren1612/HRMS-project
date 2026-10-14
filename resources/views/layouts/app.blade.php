<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/favicon.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- Apply saved theme BEFORE paint to avoid a flash of the wrong theme --}}
        <script>
            (function () {
                try {
                    var t = localStorage.getItem('theme');
                    if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    }
                } catch (e) {}
            })();
        </script>

        {{-- Dark-mode overrides (scoped to html.dark). Written as raw CSS so it works
             regardless of Tailwind's compiled dark: variants. --}}
        <style>
            html.dark body { background-color: #0f172a; color: #e2e8f0; }
            html.dark .bg-white { background-color: #1e293b !important; }
            html.dark .bg-gray-100 { background-color: #0f172a !important; }
            html.dark .bg-gray-50 { background-color: #243044 !important; }
            html.dark .hover\:bg-gray-50:hover { background-color: #334155 !important; }
            html.dark .text-gray-900 { color: #f1f5f9 !important; }
            html.dark .text-gray-800 { color: #e2e8f0 !important; }
            html.dark .text-gray-700 { color: #cbd5e1 !important; }
            html.dark .text-gray-600 { color: #a9b6c6 !important; }
            html.dark .text-gray-500 { color: #94a3b8 !important; }
            html.dark .text-gray-400 { color: #7c8aa0 !important; }
            html.dark .border-gray-100,
            html.dark .border-gray-200,
            html.dark .border-gray-300 { border-color: #334155 !important; }
            html.dark .border-b { border-bottom-color: #334155; }
            html.dark .border-t { border-top-color: #334155; }
            html.dark .divide-gray-100 > :not([hidden]) ~ :not([hidden]),
            html.dark .divide-gray-200 > :not([hidden]) ~ :not([hidden]) { border-color: #334155 !important; }
            html.dark input, html.dark textarea, html.dark select {
                background-color: #1e293b !important; color: #e2e8f0 !important; border-color: #334155 !important;
            }
            {{-- Native "Choose File" button (file: utilities can't be reached by class overrides) --}}
            html.dark input[type="file"]::file-selector-button {
                background-color: #334155 !important; color: #e2e8f0 !important; border: 0 !important;
            }
            html.dark input[type="file"]:hover::file-selector-button { background-color: #3f4d63 !important; }

            {{-- Cards/badges that use INLINE light background colors (can't be reached by
                 class selectors) — re-map each light tint to a dark surface. --}}
            html.dark [style*="#eaf5fe"] { background-color: #1b2a41 !important; }  /* hero card + icon badges */
            html.dark [style*="#f8fbff"] { background-color: #243044 !important; }  /* schedule rows */
            html.dark [style*="#f5fbff"] { background-color: #243044 !important; }  /* attendance summary tiles */
            html.dark [style*="#f1f5f9"] { background-color: #334155 !important; }  /* absent icon badge */
            html.dark [style*="#dbeefb"] { background-color: #1e3a5f !important; }  /* active status pill */
            html.dark [style*="#f3f4f6"] { border-color: #334155 !important; }      /* light divider lines */

            {{-- More surfaces + hover states --}}
            html.dark .bg-gray-200 { background-color: #334155 !important; }
            html.dark .hover\:bg-gray-100:hover { background-color: #334155 !important; }

            {{-- Coloured status badges (bg-*-100 + text-*-800/700): dark translucent fill, light text --}}
            html.dark .bg-green-100  { background-color: rgba(34,197,94,0.18) !important; }
            html.dark .bg-red-100    { background-color: rgba(239,68,68,0.18) !important; }
            html.dark .bg-blue-100   { background-color: rgba(59,130,246,0.20) !important; }
            html.dark .bg-indigo-100 { background-color: rgba(99,102,241,0.20) !important; }
            html.dark .bg-yellow-100 { background-color: rgba(234,179,8,0.18) !important; }
            html.dark .text-green-800, html.dark .text-green-700 { color: #86efac !important; }
            html.dark .text-red-800,   html.dark .text-red-700   { color: #fca5a5 !important; }
            html.dark .text-blue-800,  html.dark .text-blue-700  { color: #93c5fd !important; }
            html.dark .text-indigo-800 { color: #a5b4fc !important; }
            html.dark .text-yellow-800, html.dark .text-yellow-700 { color: #fde68a !important; }

            {{-- Alert / flash boxes (bg-*-50) --}}
            html.dark .bg-green-50  { background-color: rgba(34,197,94,0.12) !important; }
            html.dark .bg-red-50    { background-color: rgba(239,68,68,0.12) !important; }
            html.dark .bg-blue-50   { background-color: rgba(59,130,246,0.12) !important; }
            html.dark .bg-indigo-50 { background-color: rgba(99,102,241,0.12) !important; }
            html.dark .bg-yellow-50 { background-color: rgba(234,179,8,0.12) !important; }

            {{-- Theme-toggle icon visibility: moon in light mode, sun in dark mode --}}
            .theme-icon-sun { display: none; }
            html.dark .theme-icon-sun { display: block; }
            html.dark .theme-icon-moon { display: none; }
        </style>
    </head>
    <body class="font-sans antialiased">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen bg-gray-100">

            {{-- Left sidebar --}}
            @include('layouts.sidebar')

            {{-- Mobile overlay --}}
            <div x-show="sidebarOpen" @click="sidebarOpen = false" x-transition.opacity
                 class="fixed inset-0 bg-black/30 z-20 lg:hidden" style="display:none;"></div>

            {{-- Main area --}}
            <div class="lg:pl-64">

                {{-- Top bar --}}
                <header class="bg-white border-b border-gray-200 sticky top-0 z-10">
                    <div class="flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8">
                        <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden text-gray-500 hover:text-gray-700">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <div class="hidden lg:block"></div>

                        <div class="flex items-center gap-4">
                            {{-- Dark / light mode toggle --}}
                            <button type="button"
                                    @click="const h = document.documentElement; const d = h.classList.toggle('dark'); localStorage.setItem('theme', d ? 'dark' : 'light');"
                                    class="inline-flex items-center p-2 text-gray-500 hover:text-gray-700 focus:outline-none"
                                    aria-label="Toggle dark mode" title="Toggle dark / light mode">
                                {{-- Moon: shown in light mode (click → dark) --}}
                                <svg class="theme-icon-moon h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                                {{-- Sun: shown in dark mode (click → light) --}}
                                <svg class="theme-icon-sun h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m8.66-9h-1M4.34 12h-1m15.36 6.36l-.71-.71M6.34 6.34l-.71-.71m12.02 0l-.71.71M6.34 17.66l-.71.71M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            </button>

                            {{-- Notifications --}}
                            @php($unread = Auth::user()->unreadNotifications)
                            <x-dropdown align="right" width="w-80">
                                <x-slot name="trigger">
                                    <button class="relative inline-flex items-center p-2 text-gray-500 hover:text-gray-700 focus:outline-none">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                        @if ($unread->count() > 0)
                                            <span class="absolute top-0 right-0 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-600 rounded-full">{{ $unread->count() }}</span>
                                        @endif
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                    <div class="px-4 py-2 flex items-center justify-between border-b">
                                        <span class="text-sm font-semibold text-gray-700">Notifications</span>
                                        @if ($unread->count() > 0)
                                            <form method="POST" action="{{ route('notifications.readAll') }}">@csrf<button type="submit" class="text-xs hover:underline" style="color:#2f80ed;">Mark all read</button></form>
                                        @endif
                                    </div>
                                    @forelse ($unread->take(5) as $notification)
                                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                            @csrf
                                            <button type="submit" class="block w-full text-left px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 border-b border-gray-100">
                                                <div>{{ $notification->data['message'] ?? 'Notification' }}</div>
                                                <div class="text-xs text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</div>
                                            </button>
                                        </form>
                                    @empty
                                        <div class="px-4 py-4 text-sm text-gray-500 text-center">No new notifications</div>
                                    @endforelse
                                    <a href="{{ route('notifications.index') }}" class="block px-4 py-2 text-center text-xs hover:bg-gray-50" style="color:#2f80ed;">View all notifications</a>
                                </x-slot>
                            </x-dropdown>

                            {{-- User menu --}}
                            <x-dropdown align="right" width="48">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-800 focus:outline-none">
                                        @if (Auth::user()->profilePhotoUrl())
                                            <img src="{{ Auth::user()->profilePhotoUrl() }}" alt="avatar" class="h-8 w-8 rounded-full object-cover">
                                        @else
                                            <span class="h-8 w-8 rounded-full flex items-center justify-center text-white text-xs font-bold" style="background-color:#2f80ed;">{{ Auth::user()->initial() }}</span>
                                        @endif
                                        <span class="hidden sm:block">{{ Auth::user()->name }}</span>
                                        <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                    <div class="px-4 py-2 border-b">
                                        <div class="text-sm font-medium text-gray-800">{{ Auth::user()->name }}</div>
                                        <div class="text-xs text-gray-500 uppercase">{{ Auth::user()->role }}</div>
                                    </div>
                                    <x-dropdown-link :href="route('profile.edit')">{{ __('Profile') }}</x-dropdown-link>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">{{ __('Log Out') }}</x-dropdown-link>
                                    </form>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    </div>
                </header>

                {{-- Page heading --}}
                @isset($header)
                    <div class="bg-white border-b border-gray-200">
                        <div class="px-4 sm:px-6 lg:px-8 py-5">
                            {{ $header }}
                        </div>
                    </div>
                @endisset

                {{-- Page content --}}
                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
