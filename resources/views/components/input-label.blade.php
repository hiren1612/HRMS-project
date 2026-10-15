@props(['value' => null, 'required' => false])

{{-- QA U2: a required field is marked with a red asterisk, and announced to
     screen readers as "(required)" since colour and punctuation alone are not
     enough. --}}
<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-gray-700']) }}>
    {{ $value ?? $slot }}
    @if ($required)
        <span class="text-red-600" aria-hidden="true">*</span>
        <span class="sr-only">(required)</span>
    @endif
</label>
