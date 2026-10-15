{{--
    Shared create/edit form. The parent view sets the <form> action and method.
    Expects optionally $announcement (when editing).
--}}
@php($announcement = $announcement ?? null)

@csrf

@if ($errors->any())
    <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
        <ul class="list-disc list-inside space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<p class="mb-4 text-xs text-gray-500">Fields marked <span class="text-red-600">*</span> are required.</p>

<div class="space-y-6">
    <div>
        <x-input-label for="title" :value="__('Title')" required />
        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full"
                      :value="old('title', $announcement?->title)" required maxlength="150" autofocus />
        <x-input-error :messages="$errors->get('title')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="body" :value="__('Message')" />
        <textarea id="body" name="body" rows="4" maxlength="2000"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('body', $announcement?->body) }}</textarea>
        <x-input-error :messages="$errors->get('body')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
            <x-input-label for="expires_at" :value="__('Stop showing after')" />
            <x-text-input id="expires_at" name="expires_at" type="date" class="mt-1 block w-full"
                          :value="old('expires_at', $announcement?->expires_at?->format('Y-m-d'))" />
            <x-input-error :messages="$errors->get('expires_at')" class="mt-2" />
            <p class="mt-1 text-xs text-gray-500">
                Leave blank to keep it on the dashboards until you unpublish it.
                Useful for a one-off notice so it drops off by itself.
            </p>
        </div>

        <div class="flex items-start pt-7">
            <label for="is_published" class="inline-flex items-start gap-2 text-sm text-gray-700">
                <input type="checkbox" id="is_published" name="is_published" value="1"
                       @checked(old('is_published', $announcement?->is_published ?? true))
                       class="mt-0.5 rounded border-gray-300">
                <span>
                    Published
                    <span class="block text-xs text-gray-500">Unpublish to hide it from every dashboard without deleting it.</span>
                </span>
            </label>
        </div>
    </div>
</div>

<div class="flex items-center gap-4 mt-6">
    <x-primary-button>{{ $submitLabel ?? __('Save') }}</x-primary-button>
    <a href="{{ route('announcements.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
</div>
