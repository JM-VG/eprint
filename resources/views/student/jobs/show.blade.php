<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Job :code', ['code' => $job->claim_code]) }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-3 gap-6">
            <section class="bg-white shadow-sm sm:rounded-lg p-6 text-center">
                <p class="text-sm text-gray-500">{{ __('Show this at the counter') }}</p>
                <p class="mt-1 text-3xl font-bold font-mono tracking-widest text-gray-900">{{ $job->claim_code }}</p>
                <div class="mt-4 flex justify-center" aria-label="{{ __('QR code for :code', ['code' => $job->claim_code]) }}">
                    {{ \SimpleSoftwareIO\QrCode\Facades\QrCode::size(180)->margin(1)->generate($job->claim_code) }}
                </div>
                <div class="mt-4"><x-job-status :status="$job->status" /></div>
            </section>

            <section class="md:col-span-2 bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-gray-500">{{ __('File') }}</dt>
                    <dd class="text-gray-900 break-all">
                        @if ($job->files_deleted_at)
                            {{ $job->original_name }} <span class="text-gray-500">({{ __('removed') }})</span>
                        @else
                            <a href="{{ route('files.jobs.show', $job) }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">{{ $job->original_name }}</a>
                        @endif
                    </dd>

                    <dt class="text-gray-500">{{ __('Pick up at') }}</dt>
                    <dd class="text-gray-900">{{ $job->location->name }}</dd>

                    <dt class="text-gray-500">{{ __('Service') }}</dt>
                    <dd class="text-gray-900">{{ $job->service->name }}</dd>

                    @if ($job->service->type === 'photo')
                        <dt class="text-gray-500">{{ __('Sets') }}</dt>
                        <dd class="text-gray-900">{{ $job->sets }}</dd>
                    @else
                        <dt class="text-gray-500">{{ __('Pages') }}</dt>
                        <dd class="text-gray-900">
                            {{ $job->page_range ?: __('All') }}
                            @if ($job->page_count) ({{ trans_choice(':count page in file|:count pages in file', $job->page_count) }}) @endif
                        </dd>

                        <dt class="text-gray-500">{{ __('Copies') }}</dt>
                        <dd class="text-gray-900">{{ $job->copies }}{{ $job->duplex ? ', '.__('both sides') : '' }}</dd>
                    @endif

                    <dt class="text-gray-500">{{ $job->final_total ? __('Final total') : __('Estimated total') }}</dt>
                    <dd class="text-gray-900 font-mono">
                        @if ($job->final_total ?? $job->estimated_total)
                            ₱{{ $job->final_total ?? $job->estimated_total }}
                        @else
                            {{ __('Confirmed at the counter') }}
                        @endif
                    </dd>

                    @if ($job->reject_reason)
                        <dt class="text-gray-500">{{ __('Reject reason') }}</dt>
                        <dd class="text-red-700">{{ $job->reject_reason }}</dd>
                    @endif

                    @if ($job->notes)
                        <dt class="text-gray-500">{{ __('Notes') }}</dt>
                        <dd class="text-gray-900">{{ $job->notes }}</dd>
                    @endif
                </dl>

                @can('update', $job)
                    <div class="flex flex-wrap gap-3 pt-2">
                        <a href="{{ route('student.jobs.edit', $job) }}" class="px-3 py-2 rounded-md border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">{{ __('Edit') }}</a>
                        <form method="POST" action="{{ route('student.jobs.destroy', $job) }}" onsubmit="return confirm('Delete this job and its file?')">
                            @csrf
                            @method('DELETE')
                            <x-danger-button>{{ __('Delete') }}</x-danger-button>
                        </form>
                    </div>
                @endcan

                <div>
                    <h3 class="font-medium text-gray-900">{{ __('History') }}</h3>
                    <ol class="mt-2 space-y-1 text-sm text-gray-600">
                        @foreach ($job->logs as $log)
                            <li>
                                <span class="text-gray-400">{{ $log->created_at->format('M j, g:i A') }}</span>
                                — {{ ucfirst($log->to_status->value) }}@if ($log->note): {{ $log->note }}@endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
