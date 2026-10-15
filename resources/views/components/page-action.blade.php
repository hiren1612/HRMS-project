@props([
    'href' => null,
    'tone' => 'primary',
])

@php
    // QA U7: page actions were styled three ways — dark uppercase on most
    // screens, blue sentence-case on Schedules, and a third blue variant for
    // the Google import. One definition, used by every page header.
    $classes = 'inline-flex items-center gap-1.5 px-4 py-2 rounded-md text-xs font-semibold uppercase tracking-wide transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ' . match ($tone) {
        'secondary' => 'bg-white border border-gray-300 text-gray-700 shadow-sm hover:bg-gray-50',
        default => 'bg-gray-800 text-white hover:bg-gray-700',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'submit', 'class' => $classes]) }}>{{ $slot }}</button>
@endif
