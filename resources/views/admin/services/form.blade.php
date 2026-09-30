<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $service->exists ? __('Edit :name', ['name' => $service->name]) : __('Add service') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ $service->exists ? route('admin.services.update', $service) : route('admin.services.store') }}"
                  class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                @csrf
                @if ($service->exists)
                    @method('PUT')
                @endif

                <div>
                    <x-input-label for="type" :value="__('Type')" />
                    <select id="type" name="type" class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="document" @selected(old('type', $service->type) === 'document')>{{ __('Document (per page)') }}</option>
                        <option value="photo" @selected(old('type', $service->type) === 'photo')>{{ __('Photo (per set)') }}</option>
                    </select>
                    <x-input-error :messages="$errors->get('type')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $service->name)" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="paper_size" :value="__('Paper size (documents)')" />
                        <x-text-input id="paper_size" name="paper_size" class="mt-1 block w-full" :value="old('paper_size', $service->paper_size)" placeholder="Short, Long, A4" />
                        <x-input-error :messages="$errors->get('paper_size')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="pieces_per_set" :value="__('Pieces per set (photos)')" />
                        <x-text-input id="pieces_per_set" name="pieces_per_set" type="number" min="1" class="mt-1 block w-full" :value="old('pieces_per_set', $service->pieces_per_set)" />
                        <x-input-error :messages="$errors->get('pieces_per_set')" class="mt-2" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="color_mode" :value="__('Color')" />
                        <select id="color_mode" name="color_mode" class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="bw" @selected(old('color_mode', $service->color_mode) === 'bw')>{{ __('Black & White') }}</option>
                            <option value="color" @selected(old('color_mode', $service->color_mode) === 'color')>{{ __('Color') }}</option>
                        </select>
                        <x-input-error :messages="$errors->get('color_mode')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="price" :value="__('Price (₱)')" />
                        <x-text-input id="price" name="price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('price', $service->price)" required />
                        <x-input-error :messages="$errors->get('price')" class="mt-2" />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600" @checked(old('is_active', $service->is_active ?? true))>
                    {{ __('Active (shown to students)') }}
                </label>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.services.index') }}" class="px-4 py-2 text-sm text-gray-700">{{ __('Cancel') }}</a>
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
