<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Station') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                @if ($current)
                    <p class="text-gray-700">
                        {{ __('You are clocked in at') }}
                        <strong class="text-gray-900">{{ $current->location->name }}</strong>
                        {{ __('since :time.', ['time' => $current->opened_at->format('g:i A')]) }}
                    </p>

                    <div class="mt-4 flex flex-wrap gap-3">
                        @foreach ($locations->where('id', '!=', $current->location_id) as $location)
                            <form method="POST" action="{{ route('staff.station.switch') }}">
                                @csrf
                                <input type="hidden" name="location_id" value="{{ $location->id }}">
                                <x-secondary-button type="submit">{{ __('Switch to :name', ['name' => $location->name]) }}</x-secondary-button>
                            </form>
                        @endforeach

                        <form method="POST" action="{{ route('staff.station.clock-out') }}">
                            @csrf
                            <x-danger-button>{{ __('Clock Out') }}</x-danger-button>
                        </form>
                    </div>
                    <x-input-error :messages="$errors->get('location_id')" class="mt-2" />
                @else
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Clock In') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Pick the location you are working at. Students see it as open while you are clocked in.') }}</p>

                    <form method="POST" action="{{ route('staff.station.store') }}" class="mt-4 space-y-3">
                        @csrf
                        <fieldset class="space-y-2">
                            <legend class="sr-only">{{ __('Location') }}</legend>
                            @foreach ($locations as $location)
                                <label class="flex items-center gap-3 p-3 border rounded-md cursor-pointer hover:bg-gray-50">
                                    <input type="radio" name="location_id" value="{{ $location->id }}" required
                                           @checked((int) old('location_id') === $location->id)
                                           class="text-indigo-600 border-gray-300">
                                    <span class="text-gray-900">{{ $location->name }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                        <x-input-error :messages="$errors->get('location_id')" />

                        <x-primary-button>{{ __('Clock In') }}</x-primary-button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
