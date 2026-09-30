<x-app-layout dark>
    @php
        $period = $summary['period'];
        $paidTotal = array_sum($summary['by_payment']);
        $gcashShare = $paidTotal > 0 ? $summary['by_payment']['gcash'] / $paidTotal * 100 : 0;
        $sourceLabels = ['online' => __('Online'), 'walkin' => __('Walk-in'), 'staff_job' => __('Staff job')];
        $sourceFills = ['online' => 'bg-brand-orange', 'walkin' => 'bg-brand-yellow', 'staff_job' => 'bg-neutral-100'];
    @endphp

    <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-white">{{ __('Admin Dashboard') }}</h1>
                <p class="text-sm text-neutral-400">{{ now()->format('l, F j, Y') }}</p>
            </div>
            <a href="{{ route('admin.reports.sales') }}" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm text-neutral-300 hover:border-brand-yellow/40 hover:text-white">{{ __('Full sales report') }}</a>
        </div>

        <section class="grid grid-cols-1 gap-4 lg:grid-cols-12">
            {{-- Today --}}
            <div class="dash-card lg:col-span-4">
                <p class="dash-eyebrow">{{ __('Sales today') }}</p>
                <a href="{{ route('admin.reports.sales', ['timeframe' => 'today']) }}" class="mt-1 flex items-baseline gap-2">
                    <span class="font-mono text-3xl font-semibold text-white">₱{{ number_format($summary['sales_today']['amount'], 2) }}</span>
                    <span class="rounded-full bg-brand-yellow/15 px-2 py-0.5 text-xs text-brand-yellow">{{ trans_choice(':count sale|:count sales', $summary['sales_today']['count']) }}</span>
                </a>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="dash-tile">
                        <p class="dash-eyebrow">{{ __('Commission today') }}</p>
                        <p class="mt-1 font-mono text-lg font-semibold text-white">₱{{ number_format($summary['sales_today']['commission'], 2) }}</p>
                    </div>
                    <a href="{{ route('admin.payouts.index') }}" class="dash-tile">
                        <p class="dash-eyebrow">{{ __('Unpaid commission') }}</p>
                        <p class="mt-1 font-mono text-lg font-semibold text-brand-yellow">₱{{ number_format($summary['unpaid_commission'], 2) }}</p>
                    </a>
                    <div class="dash-tile">
                        <p class="dash-eyebrow">{{ __('Jobs received today') }}</p>
                        <p class="mt-1 text-lg font-semibold text-white">{{ $summary['jobs_today'] }}</p>
                    </div>
                    <a href="{{ route('admin.students.index') }}" class="dash-tile {{ $summary['pending_students'] ? 'ring-1 ring-brand-orange/60' : '' }}">
                        <p class="dash-eyebrow">{{ __('Students awaiting approval') }}</p>
                        <p class="mt-1 text-lg font-semibold {{ $summary['pending_students'] ? 'text-brand-orange' : 'text-white' }}">{{ $summary['pending_students'] }}</p>
                    </a>
                </div>
            </div>

            {{-- 30-day hero --}}
            <div class="relative overflow-hidden rounded-2xl bg-brand-orange p-5 text-white lg:col-span-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium text-white/80">{{ __('Total sales · last 30 days') }}</p>
                        <div class="mt-1 flex items-baseline gap-2">
                            <span class="font-mono text-3xl font-semibold">₱{{ number_format($period['amount'], 2) }}</span>
                            @if (! is_null($period['change']))
                                <span class="rounded-full bg-neutral-950/20 px-2 py-0.5 text-xs font-medium">
                                    {{ $period['change'] >= 0 ? '▲' : '▼' }} {{ abs($period['change']) }}%
                                    <span class="sr-only">{{ __('vs previous 30 days') }}</span>
                                </span>
                            @endif
                        </div>
                    </div>
                    <dl class="flex flex-wrap gap-x-5 gap-y-2 text-xs sm:text-right">
                        <div><dt class="text-white/80">{{ __('Commission') }}</dt><dd class="font-mono text-sm font-semibold">₱{{ number_format($period['commission'], 2) }}</dd></div>
                        <div><dt class="text-white/80">{{ __('This month') }}</dt><dd class="font-mono text-sm font-semibold">₱{{ number_format($summary['month']['amount'], 2) }}</dd></div>
                        <div><dt class="text-white/80">{{ __('Transactions') }}</dt><dd class="text-sm font-semibold">{{ $period['count'] }}</dd></div>
                    </dl>
                </div>
                <x-area-chart class="mt-5" tone="hero" height="h-44" :points="$summary['trend']" :label="__('Daily sales, last 30 days')" />
            </div>
        </section>

        {{-- Jobs strip --}}
        <section class="dash-card flex flex-wrap items-center gap-x-8 gap-y-4 py-4">
            <p class="text-sm font-medium text-white">{{ __('Jobs in progress') }}</p>
            @foreach ($summary['jobs'] as $status => $count)
                <a href="{{ route('staff.queue') }}" class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/5 text-sm font-semibold {{ $count ? 'text-brand-yellow' : 'text-neutral-500' }}">{{ $count }}</span>
                    <span class="text-sm text-neutral-300">{{ ucfirst($status) }}</span>
                </a>
            @endforeach
            <div class="ms-auto flex flex-wrap gap-2">
                @foreach ($summary['locations'] as $location)
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 px-3 py-1 text-xs text-neutral-300">
                        <span class="h-1.5 w-1.5 rounded-full {{ $location['open'] ? 'bg-emerald-400' : 'bg-neutral-600' }}"></span>
                        {{ $location['name'] }}
                        <span class="text-neutral-500">{{ $location['open'] ? trans_choice('Open, :count staff|Open, :count staff', $location['staff_count']) : __('Closed') }}</span>
                    </span>
                @endforeach
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 lg:grid-cols-12">
            <div class="dash-card lg:col-span-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-medium text-white">{{ __('Sales by source') }}</h2>
                    <span class="text-xs text-neutral-500">{{ __('Last 30 days') }}</span>
                </div>
                <x-column-bars class="mt-4" :items="collect($summary['by_source'])->map(fn ($v, $k) => [
                    'label' => $sourceLabels[$k] ?? ucfirst($k),
                    'value' => $v,
                    'display' => '₱'.number_format($v, 2),
                    'fill' => $sourceFills[$k] ?? 'bg-neutral-500',
                ])->values()->all()" />
            </div>

            <div class="dash-card flex flex-col lg:col-span-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-medium text-white">{{ __('GCash share') }}</h2>
                    <span class="text-xs text-neutral-500">{{ __('Last 30 days') }}</span>
                </div>
                <x-gauge class="mx-auto mt-4 w-full max-w-[220px]" :percent="$gcashShare" :label="__('Share of sales paid by GCash')" />
                <dl class="mt-auto grid grid-cols-2 gap-2 pt-4 text-xs">
                    <div class="dash-tile"><dt class="text-neutral-400">{{ __('GCash') }}</dt><dd class="font-mono text-sm text-white">₱{{ number_format($summary['by_payment']['gcash'], 2) }}</dd></div>
                    <div class="dash-tile"><dt class="text-neutral-400">{{ __('Cash') }}</dt><dd class="font-mono text-sm text-white">₱{{ number_format($summary['by_payment']['cash'], 2) }}</dd></div>
                </dl>
            </div>

            <div class="rounded-2xl border border-brand-yellow/20 bg-gradient-to-b from-neutral-800 to-neutral-900 p-5 lg:col-span-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-medium text-white">{{ __('Top staff this month') }}</h2>
                    <a href="{{ route('admin.payouts.index') }}" class="text-xs text-brand-yellow hover:text-brand-300">{{ __('Payouts') }}</a>
                </div>
                <ol class="mt-3 space-y-1">
                    @forelse ($summary['top_staff'] as $i => $row)
                        <li class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-white/5">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold {{ $i === 0 ? 'bg-brand-yellow text-neutral-950' : 'bg-white/10 text-neutral-300' }}">{{ $i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm text-white">{{ $row['name'] }}</p>
                                <p class="text-xs text-neutral-500">{{ trans_choice(':count sale|:count sales', $row['count']) }} · ₱{{ number_format($row['amount'], 2) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-mono text-sm font-semibold text-brand-yellow">₱{{ number_format($row['commission'], 2) }}</p>
                                <p class="text-[10px] text-neutral-500">{{ __('commission') }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-neutral-500">{{ __('No sales yet this month.') }}</li>
                    @endforelse
                </ol>
            </div>
        </section>

        <section class="dash-card p-0">
            <div class="flex items-center justify-between px-5 pt-4">
                <h2 class="text-sm font-medium text-white">{{ __('Recent sales') }}</h2>
                <a href="{{ route('admin.reports.sales') }}" class="text-xs text-brand-yellow hover:text-brand-300">{{ __('View all') }}</a>
            </div>
            <div class="overflow-x-auto">
                <table class="mt-2 min-w-full text-sm">
                    <thead class="text-left text-xs text-neutral-500">
                        <tr>
                            <th class="px-5 py-2 font-medium">{{ __('When') }}</th>
                            <th class="px-5 py-2 font-medium">{{ __('Staff') }}</th>
                            <th class="px-5 py-2 font-medium">{{ __('Location') }}</th>
                            <th class="px-5 py-2 font-medium">{{ __('Source') }}</th>
                            <th class="px-5 py-2 text-right font-medium">{{ __('Amount') }}</th>
                            <th class="px-5 py-2 text-right font-medium">{{ __('Commission') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($summary['recent_sales'] as $sale)
                            <tr class="text-neutral-300">
                                <td class="whitespace-nowrap px-5 py-2.5 text-neutral-400">{{ $sale->created_at->diffForHumans() }}</td>
                                <td class="px-5 py-2.5">{{ $sale->user?->name }}</td>
                                <td class="px-5 py-2.5">{{ $sale->location?->name }}</td>
                                <td class="px-5 py-2.5">{{ $sourceLabels[$sale->source?->value] ?? '—' }} · {{ ucfirst($sale->payment_method?->value ?? '') }}</td>
                                <td class="px-5 py-2.5 text-right font-mono text-white">₱{{ number_format($sale->amount, 2) }}</td>
                                <td class="px-5 py-2.5 text-right font-mono text-brand-yellow">₱{{ number_format($sale->commission_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-6 text-center text-neutral-500">{{ __('No sales yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
