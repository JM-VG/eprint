<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('New Photo Job') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('student.photos.store') }}" enctype="multipart/form-data"
                  data-estimate-form data-estimate-url="{{ route('api.price-estimate') }}"
                  class="bg-white shadow-sm sm:rounded-lg p-6 space-y-5">
                @csrf

                <div>
                    <x-input-label for="location_id" :value="__('Pick up at')" />
                    <select id="location_id" name="location_id" required class="mt-1 block w-full rounded-md border-gray-300">
                        @foreach ($locations as $location)
                            <option value="{{ $location['id'] }}" @selected((int) old('location_id') === $location['id'])>
                                {{ $location['name'] }} — {{ $location['open'] ? __('Open') : __('Closed') }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('location_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="file" :value="__('Photo (JPG or PNG, max 20 MB)')" />
                    <input id="file" name="file" type="file" required accept=".jpg,.jpeg,.png" class="mt-1 block w-full text-sm">
                    <x-input-error :messages="$errors->get('file')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="service_id" :value="__('Size')" />
                        <select id="service_id" name="service_id" required class="mt-1 block w-full rounded-md border-gray-300">
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}" @selected((int) old('service_id') === $service->id)>
                                    {{ $service->name }} — {{ $service->pieces_per_set }} {{ __('pcs') }}, ₱{{ $service->price }} / {{ __('set') }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('service_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="sets" :value="__('Sets')" />
                        <x-text-input id="sets" name="sets" type="number" min="1" max="100" class="mt-1 block w-full" :value="old('sets', 1)" required />
                        <x-input-error :messages="$errors->get('sets')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <x-input-label for="notes" :value="__('Notes for staff (optional)')" />
                    <textarea id="notes" name="notes" rows="2" maxlength="500" class="mt-1 block w-full rounded-md border-gray-300">{{ old('notes') }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                </div>

                <div data-estimate class="rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-700" aria-live="polite">
                    {{ __('Pick a size and number of sets to see the price.') }}
                </div>

                <div class="flex justify-end">
                    <x-primary-button>{{ __('Submit photo job') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
