@props(['status'])

@php
    $classes = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'approved' => 'bg-green-100 text-green-800',
        'rejected' => 'bg-red-100 text-red-800',
        'cancelled' => 'bg-gray-200 text-gray-700',
    ][$status] ?? 'bg-gray-100 text-gray-800';
@endphp

<span {{ $attributes->merge(['class' => "px-2 py-0.5 rounded-full text-xs font-semibold capitalize $classes"]) }}>
    {{ $status }}
</span>
