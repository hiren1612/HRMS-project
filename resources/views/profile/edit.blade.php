@section('title', 'My Profile')

@php
    // QA U13: the profile page was five stacked cards you had to scroll
    // through, one of which rendered as an empty white box for anyone who is
    // not an admin. The same sections are now tabs.
    //
    // Which tab opens is decided here rather than in JavaScript, so a failed
    // save lands on the tab that holds the error instead of the first one.
    $tabs = [
        'profile' => 'Profile',
        'account' => 'Account',
        'password' => 'Password',
    ];

    if (Auth::user()->isAdmin()) {
        $tabs['danger'] = 'Close account';
    }

    $openTab = 'profile';

    if ($errors->updatePassword->isNotEmpty()) {
        $openTab = 'password';
    } elseif ($errors->userDeletion->isNotEmpty()) {
        $openTab = 'danger';
    } elseif ($errors->getBag('default')->hasAny(['name', 'email'])) {
        $openTab = 'account';
    } elseif (session('status') === 'password-updated') {
        $openTab = 'password';
    }
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8"
             x-data="{ tab: @js($openTab) }">

            {{-- Tab strip. Scrolls sideways rather than wrapping on a phone. --}}
            <div class="border-b border-gray-200 overflow-x-auto">
                <nav class="-mb-px flex gap-1 sm:gap-6 min-w-max" role="tablist" aria-label="Profile sections">
                    @foreach ($tabs as $key => $label)
                        <button type="button" role="tab"
                                x-on:click="tab = @js($key)"
                                x-bind:aria-selected="tab === @js($key) ? 'true' : 'false'"
                                x-bind:class="tab === @js($key)
                                    ? 'border-indigo-500 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="whitespace-nowrap border-b-2 px-3 sm:px-1 py-3 text-sm font-medium transition">
                            {{ $label }}
                        </button>
                    @endforeach
                </nav>
            </div>

            <div class="mt-6 space-y-6">

                {{-- Profile: photo and the HR record the employee maintains. --}}
                <div x-show="tab === 'profile'" x-cloak role="tabpanel" class="space-y-6">
                    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                        <div class="max-w-xl">
                            @include('profile.partials.update-photo-form')
                        </div>
                    </div>

                    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                        <div class="max-w-xl">
                            @include('profile.partials.employee-profile-form')
                        </div>
                    </div>
                </div>

                {{-- Account: the login details. --}}
                <div x-show="tab === 'account'" x-cloak role="tabpanel">
                    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                        <div class="max-w-xl">
                            @include('profile.partials.update-profile-information-form')
                        </div>
                    </div>
                </div>

                <div x-show="tab === 'password'" x-cloak role="tabpanel">
                    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                        <div class="max-w-xl">
                            @include('profile.partials.update-password-form')
                        </div>
                    </div>
                </div>

                {{-- Staff must not be able to erase their own HR record (QA H4);
                     HR sets someone to Inactive instead. The tab only exists
                     for admins, so the empty card non-admins used to see is
                     gone rather than merely empty. --}}
                @if (Auth::user()->isAdmin())
                    <div x-show="tab === 'danger'" x-cloak role="tabpanel">
                        <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                            <div class="max-w-xl">
                                @include('profile.partials.delete-user-form')
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
</x-app-layout>
