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
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('No jobs waiting at :location.', ['location' => $location->name]) }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
