@props([
    'action',
    'method' => 'DELETE',
    'label' => 'Delete',
    'title' => 'Are you sure?',
    'message' => 'This cannot be undone.',
    'confirm' => 'Delete',
    'tone' => 'danger',
    'fields' => [],
])

@php
    $triggerClass = $tone === 'danger'
        ? 'text-sm font-medium text-red-600 hover:underline'
        : 'text-sm font-medium text-blue-600 hover:underline';
@endphp

{{--
    QA U8: the browser's own confirm() box is unstyled, shows the site's
    hostname and cannot be read by anyone using the keyboard comfortably.
    This is the same guard rail in the application's own theme.
--}}
<div x-data="{ open: false }" class="inline-block">
    <button type="button" x-on:click="open = true" class="{{ $triggerClass }}">{{ $label }}</button>

    <div x-show="open" x-cloak
         x-on:keydown.escape.window="open = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-label="{{ $title }}">
        <div class="absolute inset-0 bg-gray-900/50" x-on:click="open = false"></div>

        <div class="relative w-full max-w-sm rounded-lg bg-white p-6 text-left shadow-xl">
            <div class="flex items-start gap-3">
                <span @class([
                    'flex h-10 w-10 flex-none items-center justify-center rounded-full',
                    'bg-red-100 text-red-600' => $tone === 'danger',
                    'bg-blue-100 text-blue-600' => $tone !== 'danger',
                ])>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                </span>
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $message }}</p>
                </div>
            </div>

            <form method="POST" action="{{ $action }}" class="mt-5 flex items-center justify-end gap-3">
                @csrf
                @if (! in_array(strtoupper($method), ['GET', 'POST'], true))
                    @method($method)
                @endif
                @foreach ($fields as $field => $value)
                    <input type="hidden" name="{{ $field }}" value="{{ $value }}">
                @endforeach

                <button type="button" x-on:click="open = false"
                        class="text-sm text-gray-600 hover:underline">Cancel</button>

                @if ($tone === 'danger')
                    <x-danger-button>{{ $confirm }}</x-danger-button>
                @else
                    <x-primary-button>{{ $confirm }}</x-primary-button>
                @endif
            </form>
        </div>
    </div>
</div>
