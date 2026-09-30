<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $announcement->exists ? __('Edit announcement') : __('New announcement') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ $announcement->exists ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}"
                  class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                @csrf
                @if ($announcement->exists)
                    @method('PUT')
                @endif

                <div>
                    <x-input-label for="title" :value="__('Title')" />
                    <x-text-input id="title" name="title" class="mt-1 block w-full" maxlength="150" :value="old('title', $announcement->title)" required />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="body" :value="__('Message')" />
                    <textarea id="body" name="body" rows="4" maxlength="2000" required class="mt-1 block w-full rounded-md border-gray-300">{{ old('body', $announcement->body) }}</textarea>
                    <x-input-error :messages="$errors->get('body')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="starts_at" :value="__('Show from (blank = now)')" />
                        <input id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at', $announcement->starts_at?->format('Y-m-d\TH:i')) }}" class="mt-1 block w-full rounded-md border-gray-300">
                        <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ends_at" :value="__('Show until (blank = until removed)')" />
                        <input id="ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at', $announcement->ends_at?->format('Y-m-d\TH:i')) }}" class="mt-1 block w-full rounded-md border-gray-300">
                        <x-input-error :messages="$errors->get('ends_at')" class="mt-2" />
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.announcements.index') }}" class="px-4 py-2 text-sm text-gray-700">{{ __('Cancel') }}</a>
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
