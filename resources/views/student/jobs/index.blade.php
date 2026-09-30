<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('My Jobs') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('Claim code') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('File') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Location') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('Total') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Submitted') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($jobs as $job)
                            <tr>
                                <td class="px-4 py-3 font-mono">
                                    <a href="{{ route('student.jobs.show', $job) }}" class="text-indigo-600 hover:underline">{{ $job->claim_code }}</a>
                                </td>
                                <td class="px-4 py-3 truncate max-w-xs">{{ $job->original_name }}</td>
                                <td class="px-4 py-3">{{ $job->location->name }}</td>
                                <td class="px-4 py-3"><x-job-status :status="$job->status" /></td>
                                <td class="px-4 py-3 text-right font-mono">
                                    {{ $job->final_total ?? $job->estimated_total ? '₱'.($job->final_total ?? $job->estimated_total) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $job->created_at->format('M j, g:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                    {{ __('No jobs yet.') }}
                                    <a href="{{ route('student.jobs.create') }}" class="text-indigo-600 hover:underline">{{ __('Submit your first print job') }}</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $jobs->links() }}
        </div>
    </div>
</x-app-layout>
