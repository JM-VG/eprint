<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Admin Dashboard') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <section class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <a href="{{ route('admin.reports.sales', ['timeframe' => 'today']) }}" class="bg-white shadow-sm rounded-lg p-4 hover:bg-gray-50">
                    <p class="text-xs text-gray-500">{{ __('Sales today') }}</p>
                    <p class="mt-1 text-2xl font-semibold font-mono">₱{{ $summary['sales_today']['amount'] }}</p>
                    <p class="text-xs text-gray-500">{{ trans_choice(':count sale|:count sales', $summary['sales_today']['count']) }}</p>
                </a>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs text-gray-500">{{ __('Commission today') }}</p>
                    <p class="mt-1 text-2xl font-semibold font-mono">₱{{ $summary['sales_today']['commission'] }}</p>
                </div>
                <a href="{{ route('admin.payouts.index') }}" class="bg-white shadow-sm rounded-lg p-4 hover:bg-gray-50">
                    <p class="text-xs text-gray-500">{{ __('Unpaid commission') }}</p>
                    <p class="mt-1 text-2xl font-semibold font-mono">₱{{ $summary['unpaid_commission'] }}</p>
                </a>
                <a href="{{ route('admin.students.index') }}" class="bg-white shadow-sm rounded-lg p-4 hover:bg-gray-50 {{ $summary['pending_students'] ? 'ring-2 ring-amber-300' : '' }}">
                    <p class="text-xs text-gray-500">{{ __('Students awaiting approval') }}</p>
                    <p class="mt-1 text-2xl font-semibold">{{ $summary['pending_students'] }}</p>
                </a>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs text-gray-500">{{ __('Jobs in progress') }}</p>
                    <p class="mt-1 text-sm">
                        @foreach ($summary['jobs'] as $status => $count)
                            <span class="block">{{ ucfirst($status) }}: <strong>{{ $count }}</strong></span>
                        @endforeach
                    </p>
                </div>
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg p-4">
                <h3 class="font-medium text-gray-900">{{ __('Locations') }}</h3>
                <ul class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                    @foreach ($summary['locations'] as $location)
                        <li class="flex items-center justify-between rounded-md border p-3">
                            <span>{{ $location['name'] }}</span>
                            <span class="{{ $location['open'] ? 'text-green-700' : 'text-gray-500' }}">
                                {{ $location['open'] ? trans_choice('Open, :count staff|Open, :count staff', $location['staff_count']) : __('Closed') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>
</x-app-layout>
