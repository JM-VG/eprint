<x-app-layout dark>
    @php
        $commission = $summary['commission'];
        $trendTotal = array_sum(array_column($summary['trend'], 'value'));
    @endphp

    <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-white">{{ __('Welcome back, :name', ['name' => auth()->user()->first_name ?? auth()->user()->name]) }}</h1>
                <p class="text-sm text-neutral-400">{{ now()->format('l, F j, Y') }}</p>
            </div>
            <a href="{{ route('staff.station') }}" class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm {{ $summary['station'] ? 'border-emerald-400/30 text-emerald-300' : 'border-white/10 text-neutral-400 hover:text-white' }}">
                <span class="h-1.5 w-1.5 rounded-full {{ $summary['station'] ? 'bg-emerald-400' : 'bg-neutral-600' }}"></span>
                {{ $summary['station'] ?? __('Not clocked in') }}
            </a>
        </div>

        <section class="grid grid-cols-1 gap-4 lg:grid-cols-12">
            <div class="dash-card lg:col-span-4">
                <p class="dash-eyebrow">{{ __('Commission today') }}</p>
                <a href="{{ route('staff.commission') }}" class="mt-1 flex items-baseline gap-2">
                    <span class="font-mono text-3xl font-semibold text-white">₱{{ number_format($summary['commission_today'], 2) }}</span>
                    <span class="rounded-full bg-brand-yellow/15 px-2 py-0.5 text-xs text-brand-yellow">{{ trans_choice(':count sale|:count sales', $summary['sales_today']['count']) }}</span>
                </a>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="dash-tile">
                        <p class="dash-eyebrow">{{ __('My sales today') }}</p>
                        <p class="mt-1 font-mono text-lg font-semibold text-white">₱{{ number_format($summary['sales_today']['amount'], 2) }}</p>
                    </div>
                    <a href="{{ route('staff.commission') }}" class="dash-tile">
                        <p class="dash-eyebrow">{{ __('Unpaid balance') }}</p>
                        <p class="mt-1 font-mono text-lg font-semibold text-brand-yellow">₱{{ number_format($summary['unpaid'], 2) }}</p>
                    </a>
                    <div class="dash-tile">
                        <p class="dash-eyebrow">{{ __('This week') }}</p>
                        <p class="mt-1 font-mono text-lg font-semibold text-white">₱{{ number_format($commission['week'], 2) }}</p>
                    </div>
                    <div class="dash-tile">
                        <p class="dash-eyebrow">{{ __('This month') }}</p>
                        <p class="mt-1 font-mono text-lg font-semibold text-white">₱{{ number_format($commission['month'], 2) }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-brand-orange p-5 text-white lg:col-span-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium text-white/80">{{ __('My commission · last 14 days') }}</p>
                        <p class="mt-1 font-mono text-3xl font-semibold">₱{{ number_format($trendTotal, 2) }}</p>
                    </div>
                    <dl class="flex flex-wrap gap-x-5 gap-y-2 text-xs sm:text-right">
                        <div><dt class="text-white/80">{{ __('All time') }}</dt><dd class="font-mono text-sm font-semibold">₱{{ number_format($commission['all_time'], 2) }}</dd></div>
                        <div><dt class="text-white/80">{{ __('Jobs handled') }}</dt><dd class="text-sm font-semibold">{{ $commission['jobs_handled'] }}</dd></div>
                    </dl>
                </div>
                <x-area-chart class="mt-5" tone="hero" height="h-44" :points="$summary['trend']" :label="__('Daily commission, last 14 days')" />
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 lg:grid-cols-12">
            <div class="grid grid-cols-2 gap-4 lg:col-span-4">
                <a href="{{ route('staff.queue') }}" class="dash-card transition-colors hover:border-brand-yellow/30">
                    <p class="dash-eyebrow">{{ __('Pending here') }}</p>
                    <p class="mt-2 text-3xl font-semibold {{ $summary['pending_here'] ? 'text-brand-yellow' : 'text-white' }}">{{ $summary['pending_here'] }}</p>
                    <p class="mt-1 text-xs text-neutral-500">{{ __('Open queue →') }}</p>
                </a>
                <a href="{{ route('staff.walkin.create') }}" class="flex flex-col justify-between rounded-2xl bg-brand-yellow p-5 text-neutral-950 transition hover:bg-brand-300">
                    <x-icon name="walkin-sale" class="h-6 w-6" />
                    <p class="text-sm font-semibold">{{ __('New walk-in sale') }}</p>
                </a>
            </div>

            <div class="dash-card p-0 lg:col-span-8">
                <div class="flex items-center justify-between px-5 pt-4">
                    <h2 class="text-sm font-medium text-white">{{ __('My recent sales') }}</h2>
                    <a href="{{ route('staff.commission') }}" class="text-xs text-brand-yellow hover:text-brand-300">{{ __('View all') }}</a>
                </div>
                <ul class="mt-2 divide-y divide-white/5 text-sm">
                    @forelse ($summary['recent_sales'] as $sale)
                        <li class="flex items-center gap-4 px-5 py-2.5">
                            <span class="w-24 shrink-0 text-xs text-neutral-500">{{ $sale->created_at->diffForHumans(short: true) }}</span>
                            <span class="flex-1 truncate text-neutral-300">{{ $sale->location?->name }}</span>
                            <span class="font-mono text-white">₱{{ number_format($sale->amount, 2) }}</span>
                            <span class="w-20 text-right font-mono text-brand-yellow">+₱{{ number_format($sale->commission_amount, 2) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-neutral-500">{{ __('No sales yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </section>
    </div>
</x-app-layout>
