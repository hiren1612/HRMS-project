<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'HRMS') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/favicon.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex">

            {{-- Left brand panel --}}
            <div class="hidden lg:flex lg:w-1/2 flex-col justify-between p-12 text-white relative overflow-hidden"
                 style="background: linear-gradient(135deg, #00334f 0%, #2ea3f2 100%);">

                {{-- Decorative circles --}}
                <div class="absolute -top-16 -right-16 h-64 w-64 rounded-full opacity-20" style="background:#8acbf8;"></div>
                <div class="absolute bottom-10 -left-10 h-40 w-40 rounded-full opacity-10" style="background:#ffffff;"></div>

                {{-- Brand --}}
                <div class="relative flex items-center gap-3">
                    <span class="rounded-xl bg-white px-3 py-2 inline-flex items-center">
                        <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" class="h-7 w-auto">
                    </span>
                </div>

                {{-- Headline --}}
                <div class="relative">
                    <h1 class="text-4xl font-bold leading-tight">Manage your workforce, effortlessly.</h1>
                    <p class="mt-4 text-blue-100 text-lg">Attendance, leaves, goals and employee records — all in one secure place.</p>

                    <ul class="mt-8 space-y-3 text-blue-50">
                        <li class="flex items-center gap-3">
                            <span class="h-6 w-6 rounded-full bg-white/20 flex items-center justify-center"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></span>
                            Real-time attendance &amp; productive hours
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="h-6 w-6 rounded-full bg-white/20 flex items-center justify-center"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></span>
                            Leave management with approvals
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="h-6 w-6 rounded-full bg-white/20 flex items-center justify-center"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></span>
                            Role-based dashboards &amp; reports
                        </li>
                    </ul>
                </div>

                <div class="relative text-sm text-blue-200">&copy; {{ now()->year }} {{ config('app.name', 'HRMS') }}. All rights reserved.</div>
            </div>

            {{-- Right form panel --}}
            <div class="flex-1 flex items-center justify-center px-6 py-12 bg-gray-50">
                <div class="w-full max-w-md">
                    {{-- Brand for small screens --}}
                    <div class="lg:hidden flex items-center justify-center mb-8">
                        <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" class="h-8 w-auto">
                    </div>

                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
