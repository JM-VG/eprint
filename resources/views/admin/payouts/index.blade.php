<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Payouts') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <h3 class="font-medium text-gray-900">{{ __('New payout') }}</h3>

                <form method="GET" action="{{ route('admin.payouts.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end text-sm">
                    <div>
                        <label for="user_id" class="block text-gray-600">{{ __('Staff') }}</label>
                        <select id="user_id" name="user_id" required class="mt-1 w-full rounded-md border-gray-300">
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($staff as $member)
                                <option value="{{ $member->id }}" @selected((int) ($filters['user_id'] ?? 0) === $member->id)>{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="from" class="block text-gray-600">{{ __('From') }}</label>
                        <input id="from" type="date" name="from" required value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-md border-gray-300">
                    </div>
                    <div>
                        <label for="to" class="block text-gray-600">{{ __('To') }}</label>
                        <input id="to" type="date" name="to" required value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-md border-gray-300">
                    </div>
                    <x-secondary-button type="submit">{{ __('Preview') }}</x-secondary-button>
                </form>

                @if ($preview)
                    <div class="rounded-md bg-gray-50 p-4 text-sm space-y-3">
                        <p>
                            {{ trans_choice(':count unpaid sale|:count unpaid sales', $preview['count']) }} —
                            <strong class="text-lg">₱{{ $preview['amount'] }}</strong> {{ __('commission') }}
                        </p>

                        @if ($preview['count'] > 0)
                            <form method="POST" action="{{ route('admin.payouts.store') }}" class="flex flex-wrap items-end gap-3"
                                  onsubmit="return confirm('Record this payout as paid?')">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $filters['user_id'] }}">
                                <input type="hidden" name="from" value="{{ $filters['from'] }}">
                                <input type="hidden" name="to" value="{{ $filters['to'] }}">
                                <div class="flex-1 min-w-48">
                                    <label for="notes" class="block text-gray-600">{{ __('Notes (optional)') }}</label>
                                    <input id="notes" name="notes" maxlength="500" class="mt-1 w-full rounded-md border-gray-300">
                                </div>
                                <x-primary-button>{{ __('Record payout') }}</x-primary-button>
                            </form>
                        @endif
                    </div>
                @endif

                @if ($errors->any())
                    <p class="text-sm text-red-600">{{ $errors->first() }}</p>
                @endif
            </section>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('Paid') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Staff') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Period') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('Amount') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Paid by') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($payouts as $payout)
                            <tr>
                                <td class="px-4 py-3">{{ $payout->paid_at->format('M j, Y g:i A') }}</td>
                                <td class="px-4 py-3">{{ $payout->user->name }}</td>
                                <td class="px-4 py-3">{{ $payout->period_start->format('M j') }} – {{ $payout->period_end->format('M j, Y') }}</td>
                                <td class="px-4 py-3 text-right font-mono">₱{{ $payout->amount }}</td>
                                <td class="px-4 py-3">{{ $payout->payer?->name }}</td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('admin.payouts.show', $payout) }}" class="text-indigo-600 hover:underline">{{ __('Details') }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('No payouts yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $payouts->links() }}
        </div>
    </div>
</x-app-layout>
