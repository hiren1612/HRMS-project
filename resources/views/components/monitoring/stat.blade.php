@props([
    'label',
    'value',
    'hint' => null,
])

{{-- A single headline figure. Kept deliberately plain: these numbers are
     about people's working day, and a dashboard that dresses them up as a
     scoreboard invites them to be read as one. --}}
<div {{ $attributes->merge(['class' => 'bg-white shadow-sm sm:rounded-lg p-5']) }}>
    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ $label }}</p>
    <p class="mt-2 text-3xl font-bold text-gray-800">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
