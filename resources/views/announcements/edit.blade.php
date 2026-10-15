@section('title', 'Edit Announcement')

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit Announcement') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('announcements.update', $announcement) }}">
                    @method('PATCH')
                    @include('announcements._form', ['submitLabel' => __('Save Changes')])
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
