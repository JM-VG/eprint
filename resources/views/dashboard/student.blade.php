<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Dashboard') }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('student.jobs.create') }}" class="px-3 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700">{{ __('New Print Job') }}</a>
                <a href="{{ route('student.photos.create') }}" class="px-3 py-2 rounded-md border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">{{ __('New Photo Job') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @foreach ($summary['ready_jobs'] as $job)
                <a href="{{ route('student.jobs.show', $job) }}" class="block rounded-lg bg-green-50 border border-green-200 p-4 text-green-900 hover:bg-green-100">
                    <strong class="font-mono">{{ $job->claim_code }}</strong>
                    {{ __('is ready for pickup at :location. Show your claim code at the counter.', ['location' => $job->location->name]) }}
                </a>
            @endforeach

            <section class="grid grid-cols-3 gap-3">
                <div class="bg-white shadow-sm rounded-lg p-4"><p class="text-xs text-gray-500">{{ __('Active jobs') }}</p><p class="mt-1 text-2xl font-semibold">{{ $summary['active'] }}</p></div>
                <div class="bg-white shadow-sm rounded-lg p-4"><p class="text-xs text-gray-500">{{ __('Ready for pickup') }}</p><p class="mt-1 text-2xl font-semibold">{{ $summary['ready'] }}</p></div>
                <div class="bg-white shadow-sm rounded-lg p-4"><p class="text-xs text-gray-500">{{ __('Completed') }}</p><p class="mt-1 text-2xl font-semibold">{{ $summary['completed'] }}</p></div>
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg">
                <div class="flex items-center justify-between px-4 pt-4">
                    <h3 class="font-medium text-gray-900">{{ __('Recent jobs') }}</h3>
                    <a href="{{ route('student.jobs.index') }}" class="text-sm text-indigo-600 hover:underline">{{ __('View all') }}</a>
                </div>
                <ul class="mt-2 divide-y divide-gray-100 text-sm">
                    @forelse ($summary['recent'] as $job)
                        <li class="flex items-center justify-between gap-4 px-4 py-3">
                            <a href="{{ route('student.jobs.show', $job) }}" class="font-mono text-indigo-600 hover:underline">{{ $job->claim_code }}</a>
                            <span class="flex-1 truncate text-gray-700">{{ $job->original_name }}</span>
                            <span class="text-gray-500">{{ $job->location->name }}</span>
                            <x-job-status :status="$job->status" />
                        </li>
                    @empty
                        <li class="px-4 py-6 text-center text-gray-500">{{ __('No jobs yet.') }}</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-app-layout>
