<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Payout to :name', ['name' => $payout->user->name]) }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <dl class="bg-white shadow-sm sm:rounded-lg p-6 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div><dt class="text-gray-500">{{ __('Amount') }}</dt><dd class="text-lg font-semibold font-mono">₱{{ $payout->amount }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Period') }}</dt><dd>{{ $payout->period_start->format('M j') }} – {{ $payout->period_end->format('M j, Y') }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Paid') }}</dt><dd>{{ $payout->paid_at->format('M j, Y g:i A') }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Paid by') }}</dt><dd>{{ $payout->payer?->name }}</dd></div>
                @if ($payout->notes)
                    <div class="col-span-2 sm:col-span-4"><dt class="text-gray-500">{{ __('Notes') }}</dt><dd>{{ $payout->notes }}</dd></div>
                @endif
            </dl>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('Date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Location') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Source') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Job') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('Amount') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('Commission') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($payout->sales as $sale)
                            <tr>
                                <td class="px-4 py-3">{{ $sale->created_at->format('M j, Y g:i A') }}</td>
                                <td class="px-4 py-3">{{ $sale->location->name }}</td>
                                <td class="px-4 py-3">{{ str_replace('_', ' ', ucfirst($sale->source->value)) }}</td>
                                <td class="px-4 py-3 font-mono">{{ $sale->printJob?->claim_code ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-mono">₱{{ $sale->amount }}</td>
                                <td class="px-4 py-3 text-right font-mono">₱{{ $sale->commission_amount }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <a href="{{ route('admin.payouts.index') }}" class="text-sm text-indigo-600 hover:underline">{{ __('Back to payouts') }}</a>
        </div>
    </div>
</x-app-layout>
