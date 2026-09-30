<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('New Print Job') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('student.jobs.store') }}" enctype="multipart/form-data"
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
                    <p class="mt-1 text-xs text-gray-500">{{ __('You can submit to a closed location; your job waits in the queue until staff open the station.') }}</p>
                    <x-input-error :messages="$errors->get('location_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="file" :value="__('File (PDF, DOCX, JPG or PNG, max 20 MB)')" />
                    <input id="file" name="file" type="file" required accept=".pdf,.docx,.jpg,.jpeg,.png" class="mt-1 block w-full text-sm">
                    <x-input-error :messages="$errors->get('file')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="service_id" :value="__('Paper and color')" />
                    <select id="service_id" name="service_id" required class="mt-1 block w-full rounded-md border-gray-300">
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}" @selected((int) old('service_id') === $service->id)>
                                {{ $service->name }} — ₱{{ $service->price }} / {{ __('page') }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('service_id')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="copies" :value="__('Copies')" />
                        <x-text-input id="copies" name="copies" type="number" min="1" max="100" class="mt-1 block w-full" :value="old('copies', 1)" required />
                        <x-input-error :messages="$errors->get('copies')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="page_range" :value="__('Pages (blank = all)')" />
                        <x-text-input id="page_range" name="page_range" class="mt-1 block w-full" :value="old('page_range')" placeholder="1-3, 5" />
                        <x-input-error :messages="$errors->get('page_range')" class="mt-2" />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="hidden" name="duplex" value="0">
                    <input type="checkbox" name="duplex" value="1" class="rounded border-gray-300 text-indigo-600" @checked(old('duplex'))>
                    {{ __('Print on both sides') }}
                </label>

                <div>
                    <x-input-label for="notes" :value="__('Notes for staff (optional)')" />
                    <textarea id="notes" name="notes" rows="2" maxlength="500" class="mt-1 block w-full rounded-md border-gray-300">{{ old('notes') }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                </div>

                <div data-estimate class="rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-700" aria-live="polite">
                    {{ __('Choose a file to see the estimated price.') }}
                </div>

                <div class="flex justify-end">
                    <x-primary-button>{{ __('Submit job') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
