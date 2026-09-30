<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('My Commission') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <section class="grid grid-cols-2 md:grid-cols-6 gap-3">
                @foreach ([
                    'today' => __('Today'),
                    'week' => __('This week'),
                    'month' => __('This month'),
                    'all_time' => __('All time'),
                    'unpaid' => __('Unpaid balance'),
                ] as $key => $label)
                    <div class="bg-white shadow-sm rounded-lg p-4 {{ $key === 'unpaid' ? 'ring-2 ring-indigo-200' : '' }}">
                        <p class="text-xs text-gray-500">{{ $label }}</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900 font-mono">₱{{ $summary[$key] }}</p>
                    </div>
                @endforeach
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs text-gray-500">{{ __('Jobs handled') }}</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">{{ $summary['jobs_handled'] }}</p>
                </div>
            </section>

            <form method="GET" action="{{ route('staff.commission') }}" class="bg-white shadow-sm sm:rounded-lg p-4 grid grid-cols-1 sm:grid-cols-4 gap-3 items-end text-sm">
                <div>
                    <label for="from" class="block text-gray-600">{{ __('From') }}</label>
                    <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-md border-gray-300">
                </div>
                <div>
                    <label for="to" class="block text-gray-600">{{ __('To') }}</label>
                    <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-md border-gray-300">
                </div>
                <div>
                    <label for="location_id" class="block text-gray-600">{{ __('Location') }}</label>
                    <select id="location_id" name="location_id" class="mt-1 w-full rounded-md border-gray-300">
                        <option value="">{{ __('All locations') }}</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((int) ($filters['location_id'] ?? 0) === $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <x-primary-button>{{ __('Filter') }}</x-primary-button>
                    <a href="{{ route('staff.commission') }}" class="px-3 py-2 text-gray-600">{{ __('Reset') }}</a>
                </div>
                @if ($errors->any())
                    <div class="sm:col-span-4 text-red-600">{{ $errors->first() }}</div>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('Date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Location') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Source') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Job') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Payment') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('Amount') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('Commission') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Paid out') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($sales as $sale)
                            <tr>
                                <td class="px-4 py-3">{{ $sale->created_at->format('M j, Y g:i A') }}</td>
                                <td class="px-4 py-3">{{ $sale->location->name }}</td>
                                <td class="px-4 py-3">{{ str_replace('_', ' ', ucfirst($sale->source->value)) }}</td>
                                <td class="px-4 py-3 font-mono">{{ $sale->printJob?->claim_code ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $sale->payment_method === \App\Enums\PaymentMethod::Gcash ? 'GCash' : 'Cash' }}</td>
                                <td class="px-4 py-3 text-right font-mono">₱{{ $sale->amount }}</td>
                                <td class="px-4 py-3 text-right font-mono">₱{{ $sale->commission_amount }} <span class="text-gray-400">({{ $sale->commission_rate }}%)</span></td>
                                <td class="px-4 py-3">{{ $sale->payout_id ? __('Yes') : __('No') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">{{ __('No sales yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $sales->links() }}
        </div>
    </div>
</x-app-layout>
