@props([
    'name',
    'label',
    'value',
    'suffix' => null,
    'hint' => null,
])

<div>
    <x-input-label :for="$name" :value="$label" />

    <div class="mt-1 flex items-center gap-2">
        <input id="{{ $name }}" name="{{ $name }}" type="number" inputmode="numeric"
               value="{{ old($name, $value) }}"
               class="block w-28 border-gray-300 rounded-md shadow-sm text-sm">

        @if ($suffix)
            <span class="text-sm text-gray-500">{{ $suffix }}</span>
        @endif
    </div>

    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif

    <x-input-error :messages="$errors->get($name)" class="mt-1" />
</div>
