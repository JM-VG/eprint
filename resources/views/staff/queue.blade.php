<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Queue') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <nav class="flex flex-wrap gap-2" aria-label="{{ __('Locations') }}">
                @foreach ($locations as $tab)
                    <a href="{{ route('staff.queue', $tab->id === $stationLocationId ? [] : ['location' => $tab->id]) }}"
                       @if ($tab->is($location)) aria-current="page" @endif
                       class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium {{ $tab->is($location) ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50' }}">
                        <span>{{ $tab->name }}</span>
                        <span class="inline-block min-w-5 px-1.5 rounded-full text-xs text-center {{ $tab->is($location) ? 'bg-white text-indigo-700' : 'bg-gray-200 text-gray-700' }}">{{ $counts[$tab->id] ?? 0 }}</span>
                        @if ($tab->id === $stationLocationId)
                            <span class="sr-only">({{ __('your station') }})</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('Code') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Student') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('File') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Service') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Pages / sets') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Waiting since') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($jobs as $job)
                            <tr>
                                <td class="px-4 py-3 font-mono">{{ $job->claim_code }}</td>
                                <td class="px-4 py-3">{{ $job->user->name }}</td>
                                <td class="px-4 py-3 max-w-xs truncate">
                                    <a href="{{ route('files.jobs.show', $job) }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">{{ $job->original_name }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $job->service->name }}</td>
                                <td class="px-4 py-3">
                                    @if ($job->service->type === 'photo')
                                        {{ trans_choice(':count set|:count sets', $job->sets ?? 1) }}
                                    @else
                                        {{ $job->page_range ?: __('All') }}{{ $job->page_count ? ' / '.$job->page_count : '' }} × {{ $job->copies }}{{ $job->duplex ? ', '.__('duplex') : '' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3"><x-job-status :status="$job->status" /></td>
                                <td class="px-4 py-3 text-gray-500">{{ $job->created_at->diffForHumans() }}</td>
                                <td class="px-4 py-3">
                                    @if ($job->status === \App\Enums\JobStatus::Pending)
                                        <div class="flex flex-col gap-2 min-w-64">
                                            <form method="POST" action="{{ route('staff.jobs.accept', $job) }}" class="flex flex-wrap items-center gap-2">
                                                @csrf
                                                <label class="sr-only" for="page_count-{{ $job->id }}">{{ __('Pages') }}</label>
                                                <input id="page_count-{{ $job->id }}" name="page_count" type="number" min="1" required
                                                       value="{{ $job->page_count }}" placeholder="{{ __('Pages') }}" class="w-20 rounded-md border-gray-300 text-sm">
                                                <label class="sr-only" for="final_total-{{ $job->id }}">{{ __('Final total') }}</label>
                                                <input id="final_total-{{ $job->id }}" name="final_total" type="number" step="0.01" min="0" required
                                                       value="{{ $job->estimated_total }}" placeholder="₱" class="w-24 rounded-md border-gray-300 text-sm">
                                                <button class="px-3 py-1.5 rounded-md bg-green-600 text-white text-sm hover:bg-green-700">{{ __('Accept') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('staff.jobs.reject', $job) }}" class="flex flex-wrap items-center gap-2">
                                                @csrf
                                                <label class="sr-only" for="reason-{{ $job->id }}">{{ __('Reason') }}</label>
                                                <input id="reason-{{ $job->id }}" name="reason" required maxlength="500" placeholder="{{ __('Reject reason') }}"
                                                       class="w-44 rounded-md border-gray-300 text-sm">
                                                <button class="px-3 py-1.5 rounded-md bg-red-600 text-white text-sm hover:bg-red-700">{{ __('Reject') }}</button>
                                            </form>
                                        </div>
                                    @elseif ($job->status === \App\Enums\JobStatus::Accepted)
                                        <form method="POST" action="{{ route('staff.jobs.printing', $job) }}">
                                            @csrf
                                            <button class="px-3 py-1.5 rounded-md bg-blue-600 text-white text-sm hover:bg-blue-700">{{ __('Start printing') }}</button>
                                        </form>
                                    @elseif ($job->status === \App\Enums\JobStatus::Printing)
                                        <form method="POST" action="{{ route('staff.jobs.ready', $job) }}">
                                            @csrf
                                            <button class="px-3 py-1.5 rounded-md bg-green-600 text-white text-sm hover:bg-green-700">{{ __('Mark ready') }}</button>
                                        </form>
                                    @else
                                        <span class="text-gray-500 text-sm">{{ __('Waiting for pickup') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-gray-500">{{ __('No jobs waiting at :location.', ['location' => $location->name]) }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
