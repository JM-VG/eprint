<x-app-layout dark>
    @php
        $s = $summary['statuses'];
        $mix = [
            ['label' => __('Pending'), 'value' => $s['pending'], 'fill' => 'bg-brand-orange'],
            ['label' => __('In progress'), 'value' => $s['accepted'] + $s['printing'], 'fill' => 'bg-brand-yellow'],
            ['label' => __('Ready'), 'value' => $s['ready'], 'fill' => 'bg-neutral-100'],
            ['label' => __('Claimed'), 'value' => $s['claimed'], 'fill' => 'bg-neutral-500'],
        ];
        $mix = array_map(fn ($m) => $m + ['display' => trans_choice(':count job|:count jobs', $m['value'])], $mix);
    @endphp

    <div class="mx-auto max-w-6xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
        <div>
            <h1 class="text-xl font-semibold text-white">{{ __('Hi, :name', ['name' => auth()->user()->first_name ?? auth()->user()->name]) }}</h1>
            <p class="text-sm text-neutral-400">{{ __('Here is what is happening with your print jobs.') }}</p>
        </div>

        @foreach ($summary['ready_jobs'] as $job)
            <a href="{{ route('student.jobs.show', $job) }}" class="flex items-center gap-3 rounded-2xl bg-brand-yellow px-5 py-4 text-neutral-950 transition hover:bg-brand-300">
                <x-icon name="claim" class="h-5 w-5 shrink-0" />
                <p class="text-sm">
                    <strong class="font-mono">{{ $job->claim_code }}</strong>
                    {{ __('is ready for pickup at :location. Show your claim code at the counter.', ['location' => $job->location->name]) }}
                </p>
            </a>
        @endforeach

        <section class="grid grid-cols-1 gap-4 lg:grid-cols-12">
            <div class="dash-card lg:col-span-4">
                <p class="dash-eyebrow">{{ __('Active jobs') }}</p>
                <p class="mt-1 text-3xl font-semibold text-white">{{ $summary['active'] }}</p>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="dash-tile">
                        <p class="dash-eyebrow">{{ __('Ready for pickup') }}</p>
                        <p class="mt-1 text-lg font-semibold {{ $summary['ready'] ? 'text-brand-yellow' : 'text-white' }}">{{ $summary['ready'] }}</p>
                    </div>
                    <div class="dash-tile">
                        <p class="dash-eyebrow">{{ __('Completed') }}</p>
                        <p class="mt-1 text-lg font-semibold text-white">{{ $summary['completed'] }}</p>
                    </div>
                    <div class="dash-tile col-span-2">
                        <p class="dash-eyebrow">{{ __('Sheets printed this month') }}</p>
                        <p class="mt-1 text-lg font-semibold text-white">{{ number_format($summary['pages_month']) }}</p>
                    </div>
                </div>
            </div>

            <div class="relative overflow-hidden rounded-2xl bg-brand-orange p-6 text-white lg:col-span-8">
                <div class="pointer-events-none absolute -right-10 -top-16 h-56 w-56 rounded-full bg-brand-yellow/40 blur-3xl" aria-hidden="true"></div>
                <div class="relative flex h-full flex-col justify-between gap-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-medium text-white/80">{{ __('Spent this month') }}</p>
                            <p class="mt-1 font-mono text-4xl font-semibold">₱{{ number_format($summary['spent_month'], 2) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-white/80">{{ __('All-time spend') }}</p>
                            <p class="font-mono text-lg font-semibold">₱{{ number_format($summary['spent_total'], 2) }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('student.jobs.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-neutral-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-neutral-800">
                            <x-icon name="new-print-job" class="h-4 w-4" /> {{ __('New Print Job') }}
                        </a>
                        <a href="{{ route('student.photos.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-white/20 px-4 py-2.5 text-sm font-medium text-white hover:bg-white/30">
                            <x-icon name="new-photo-job" class="h-4 w-4" /> {{ __('New Photo Job') }}
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 lg:grid-cols-12">
            <div class="dash-card lg:col-span-5">
                <h2 class="text-sm font-medium text-white">{{ __('Your jobs by status') }}</h2>
                <x-column-bars class="mt-4" :items="$mix" height="h-36" />
            </div>

            <div class="dash-card p-0 lg:col-span-7">
                <div class="flex items-center justify-between px-5 pt-4">
                    <h2 class="text-sm font-medium text-white">{{ __('Recent jobs') }}</h2>
                    <a href="{{ route('student.jobs.index') }}" class="text-xs text-brand-yellow hover:text-brand-300">{{ __('View all') }}</a>
                </div>
                <ul class="mt-2 divide-y divide-white/5 text-sm">
                    @forelse ($summary['recent'] as $job)
                        <li class="flex items-center justify-between gap-4 px-5 py-3">
                            <a href="{{ route('student.jobs.show', $job) }}" class="font-mono text-brand-yellow hover:underline">{{ $job->claim_code }}</a>
                            <span class="flex-1 truncate text-neutral-300">{{ $job->original_name }}</span>
                            <span class="hidden text-neutral-500 sm:inline">{{ $job->location->name }}</span>
                            <x-job-status :status="$job->status" />
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-neutral-500">{{ __('No jobs yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </section>
    </div>
</x-app-layout>
