<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit job :code', ['code' => $job->claim_code]) }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('student.jobs.update', $job) }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-5">
                @csrf
                @method('PUT')

                <p class="text-sm text-gray-600">
                    {{ __('File: :name. To change the file, delete this job and submit a new one.', ['name' => $job->original_name]) }}
                </p>

                <div>
                    <x-input-label for="location_id" :value="__('Pick up at')" />
                    <select id="location_id" name="location_id" class="mt-1 block w-full rounded-md border-gray-300">
                        @foreach ($locations as $location)
                            <option value="{{ $location['id'] }}" @selected((int) old('location_id', $job->location_id) === $location['id'])>
                                {{ $location['name'] }} — {{ $location['open'] ? __('Open') : __('Closed') }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('location_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="service_id" :value="$job->service->type === 'photo' ? __('Size') : __('Paper and color')" />
                    <select id="service_id" name="service_id" class="mt-1 block w-full rounded-md border-gray-300">
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}" @selected((int) old('service_id', $job->service_id) === $service->id)>
                                {{ $service->name }} — ₱{{ $service->price }} / {{ $service->unit }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('service_id')" class="mt-2" />
                </div>

                @if ($job->service->type === 'photo')
                    <div>
                        <x-input-label for="sets" :value="__('Sets')" />
                        <x-text-input id="sets" name="sets" type="number" min="1" max="100" class="mt-1 block w-full" :value="old('sets', $job->sets)" required />
                        <x-input-error :messages="$errors->get('sets')" class="mt-2" />
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="copies" :value="__('Copies')" />
                            <x-text-input id="copies" name="copies" type="number" min="1" max="100" class="mt-1 block w-full" :value="old('copies', $job->copies)" required />
                            <x-input-error :messages="$errors->get('copies')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="page_range" :value="__('Pages (blank = all)')" />
                            <x-text-input id="page_range" name="page_range" class="mt-1 block w-full" :value="old('page_range', $job->page_range)" placeholder="1-3, 5" />
                            <x-input-error :messages="$errors->get('page_range')" class="mt-2" />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="hidden" name="duplex" value="0">
                        <input type="checkbox" name="duplex" value="1" class="rounded border-gray-300 text-indigo-600" @checked(old('duplex', $job->duplex))>
                        {{ __('Print on both sides') }}
                    </label>
                @endif

                <div>
                    <x-input-label for="notes" :value="__('Notes for staff (optional)')" />
                    <textarea id="notes" name="notes" rows="2" maxlength="500" class="mt-1 block w-full rounded-md border-gray-300">{{ old('notes', $job->notes) }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('student.jobs.show', $job) }}" class="px-4 py-2 text-sm text-gray-700">{{ __('Cancel') }}</a>
                    <x-primary-button>{{ __('Save changes') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
