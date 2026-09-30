<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Dashboard') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-2 md:grid-cols-4 gap-3">
            <a href="{{ route('staff.station') }}" class="bg-white shadow-sm rounded-lg p-4 hover:bg-gray-50">
                <p class="text-xs text-gray-500">{{ __('Station') }}</p>
                <p class="mt-1 text-lg font-semibold">{{ $summary['station'] ?? __('Not clocked in') }}</p>
            </a>
            <a href="{{ route('staff.queue') }}" class="bg-white shadow-sm rounded-lg p-4 hover:bg-gray-50">
                <p class="text-xs text-gray-500">{{ __('Pending here') }}</p>
                <p class="mt-1 text-2xl font-semibold">{{ $summary['pending_here'] }}</p>
            </a>
            <a href="{{ route('staff.commission') }}" class="bg-white shadow-sm rounded-lg p-4 hover:bg-gray-50">
                <p class="text-xs text-gray-500">{{ __('Commission today') }}</p>
                <p class="mt-1 text-2xl font-semibold font-mono">₱{{ $summary['commission_today'] }}</p>
            </a>
            <a href="{{ route('staff.commission') }}" class="bg-white shadow-sm rounded-lg p-4 hover:bg-gray-50">
                <p class="text-xs text-gray-500">{{ __('Unpaid balance') }}</p>
                <p class="mt-1 text-2xl font-semibold font-mono">₱{{ $summary['unpaid'] }}</p>
            </a>
        </div>
    </div>
</x-app-layout>
