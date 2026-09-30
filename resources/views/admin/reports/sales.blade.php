<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Sales Report') }}</h2>
            <a href="{{ route('admin.reports.sales.export', $filters) }}" class="px-3 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700">{{ __('Export CSV') }}</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <form method="GET" action="{{ route('admin.reports.sales') }}" class="bg-white shadow-sm sm:rounded-lg p-4 grid grid-cols-2 md:grid-cols-7 gap-3 items-end text-sm">
                <div>
                    <label for="timeframe" class="block text-gray-600">{{ __('Timeframe') }}</label>
                    <select id="timeframe" name="timeframe" class="mt-1 w-full rounded-md border-gray-300">
                        @foreach (['today' => __('Today'), 'week' => __('This week'), 'month' => __('This month'), 'custom' => __('Custom range')] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['timeframe'] ?? 'month') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="from" class="block text-gray-600">{{ __('From') }}</label>
                    <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-md border-gray-300">
                </div>
                <div>
                    <label for="to" class="block text-gray-600">{{ __('To') }}</label>
                    <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-md border-gray-300">
                </div>
                <div>
                    <label for="user_id" class="block text-gray-600">{{ __('Officer') }}</label>
                    <select id="user_id" name="user_id" class="mt-1 w-full rounded-md border-gray-300">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($officers as $officer)
                            <option value="{{ $officer->id }}" @selected((int) ($filters['user_id'] ?? 0) === $officer->id)>{{ $officer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="location_id" class="block text-gray-600">{{ __('Location') }}</label>
                    <select id="location_id" name="location_id" class="mt-1 w-full rounded-md border-gray-300">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((int) ($filters['location_id'] ?? 0) === $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="source" class="block text-gray-600">{{ __('Source') }}</label>
                    <select id="source" name="source" class="mt-1 w-full rounded-md border-gray-300">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->value }}" @selected(($filters['source'] ?? '') === $source->value)>{{ str_replace('_', ' ', ucfirst($source->value)) }}</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button>{{ __('Apply') }}</x-primary-button>
                @if ($errors->any())
                    <div class="col-span-2 md:col-span-7 text-red-600">{{ $errors->first() }}</div>
                @endif
            </form>

            <p class="text-sm text-gray-600">{{ $from->format('M j, Y') }} – {{ $to->format('M j, Y') }}</p>

            <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-white shadow-sm rounded-lg p-4"><p class="text-xs text-gray-500">{{ __('Sales') }}</p><p class="mt-1 text-xl font-semibold">{{ $totals['count'] }}</p></div>
                <div class="bg-white shadow-sm rounded-lg p-4"><p class="text-xs text-gray-500">{{ __('Amount') }}</p><p class="mt-1 text-xl font-semibold font-mono">₱{{ $totals['amount'] }}</p></div>
                <div class="bg-white shadow-sm rounded-lg p-4"><p class="text-xs text-gray-500">{{ __('Commission') }}</p><p class="mt-1 text-xl font-semibold font-mono">₱{{ $totals['commission'] }}</p></div>
                <div class="bg-white shadow-sm rounded-lg p-4"><p class="text-xs text-gray-500">{{ __('Sheets used') }}</p><p class="mt-1 text-xl font-semibold">{{ $totals['sheets'] }}</p></div>
            </section>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('Date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Officer') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Location') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Source') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Job') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('Amount') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('Commission') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('Sheets') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($sales as $sale)
                            <tr>
                                <td class="px-4 py-3">{{ $sale->created_at->format('M j, Y g:i A') }}</td>
                                <td class="px-4 py-3">{{ $sale->user->name }}</td>
                                <td class="px-4 py-3">{{ $sale->location->name }}</td>
                                <td class="px-4 py-3">{{ str_replace('_', ' ', ucfirst($sale->source->value)) }}</td>
                                <td class="px-4 py-3 font-mono">{{ $sale->printJob?->claim_code ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-mono">₱{{ $sale->amount }}</td>
                                <td class="px-4 py-3 text-right font-mono">₱{{ $sale->commission_amount }}</td>
                                <td class="px-4 py-3 text-right">{{ \App\Services\SalesReportService::sheets($sale) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">{{ __('No sales in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $sales->links() }}
        </div>
    </div>
</x-app-layout>
