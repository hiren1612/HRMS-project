@props([
    'name',
    'label',
    'options' => [],
    'selected' => null,
    'required' => false,
    'placeholder' => '- Select -',
])

@php
    $current = old($name, $selected);
    // A value saved before this list existed (or typed via "Other") should
    // stay selected rather than silently reverting to the first option.
    $isOther = filled($current) && ! in_array($current, $options, true);
@endphp

{{-- QA M9: a dropdown of known values, with a free-text escape hatch so HR is
     never blocked by a role nobody has added to the list yet. The server
     resolves "Other" back into the real field, so this works without JS. --}}
<div x-data="{ choice: @js($isOther ? '__other' : ($current ?? '')) }">
    <x-input-label :for="$name" :value="$label" :required="$required" />

    <select id="{{ $name }}" name="{{ $name }}" x-model="choice"
            @class([
                'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm',
            ])>
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $option)
            <option value="{{ $option }}" @selected(! $isOther && $current === $option)>{{ $option }}</option>
        @endforeach
        <option value="__other" @selected($isOther)>Other…</option>
    </select>

    <div x-show="choice === '__other'" x-cloak class="mt-2">
        <x-text-input :id="$name . '_other'" :name="$name . '_other'" type="text" class="block w-full"
                      :value="$isOther ? $current : ''"
                      :placeholder="__('Type the :label', ['label' => Str::lower($label)])"
                      maxlength="100" />
        <p class="mt-1 text-xs text-gray-500">Saved as typed, and offered in this list from now on.</p>
    </div>

    <x-input-error :messages="$errors->get($name)" class="mt-2" />
    <x-input-error :messages="$errors->get($name . '_other')" class="mt-2" />
</div>
