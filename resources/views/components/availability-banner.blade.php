@inject('availability', 'App\Services\AvailabilityService')

{{-- Rendered server-side, then refreshed from GET /api/availability every 15 s (resources/js/availability.js). --}}
<div id="availability-banner"
     data-endpoint="{{ route('api.availability') }}"
     class="w-full bg-gray-800 text-gray-100 text-sm"
     role="status"
     aria-live="polite">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex flex-wrap gap-x-6 gap-y-1">
        @foreach ($availability->locations() as $location)
            <span data-location="{{ $location['id'] }}" class="inline-flex items-center gap-2">
                <span data-dot class="h-2 w-2 rounded-full {{ $location['open'] ? 'bg-green-400' : 'bg-red-400' }}" aria-hidden="true"></span>
                <span data-name>{{ $location['name'] }}</span>
                <span data-status class="font-semibold">{{ $location['open'] ? 'Open' : 'Closed' }}</span>
            </span>
        @endforeach
    </div>
</div>
