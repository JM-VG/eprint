<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Audit Log') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('admin.audit.index') }}" class="bg-white shadow-sm sm:rounded-lg p-4 grid grid-cols-2 md:grid-cols-6 gap-3 items-end text-sm">
                <div>
                    <label for="code" class="block text-gray-600">{{ __('Claim code') }}</label>
                    <input id="code" name="code" value="{{ $filters['code'] ?? '' }}" placeholder="EP-XXXX" class="mt-1 w-full rounded-md border-gray-300 font-mono uppercase">
                </div>
                <div>
                    <label for="user_id" class="block text-gray-600">{{ __('Actor') }}</label>
                    <select id="user_id" name="user_id" class="mt-1 w-full rounded-md border-gray-300">
                        <option value="">{{ __('Anyone') }}</option>
                        @foreach ($actors as $actor)
                            <option value="{{ $actor->id }}" @selected((int) ($filters['user_id'] ?? 0) === $actor->id)>{{ $actor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-gray-600">{{ __('Changed to') }}</label>
                    <select id="status" name="status" class="mt-1 w-full rounded-md border-gray-300">
                        <option value="">{{ __('Any status') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ ucfirst($status->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="from" class="block text-gray-600">{{ __('From') }}</label>
                    <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-md border-gray-300">
                </div>
                <div>
                    <label for="to" class="block text-gray-600">{{ __('To') }}</label>
                    <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-md border-gray-300">
                </div>
                <div class="flex gap-2">
                    <x-primary-button>{{ __('Filter') }}</x-primary-button>
                    <a href="{{ route('admin.audit.index') }}" class="px-3 py-2 text-gray-600">{{ __('Reset') }}</a>
                </div>
                @if ($errors->any())
                    <div class="col-span-2 md:col-span-6 text-red-600">{{ $errors->first() }}</div>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('When') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Job') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Actor') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Change') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Note') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($logs as $log)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $log->created_at->format('M j, Y g:i A') }}</td>
                                <td class="px-4 py-3 font-mono">{{ $log->printJob?->claim_code ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $log->user?->name ?? __('System') }}</td>
                                <td class="px-4 py-3">
                                    {{ $log->from_status ? ucfirst($log->from_status->value) : '—' }} → {{ ucfirst($log->to_status->value) }}
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $log->note }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ __('No entries match these filters.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $logs->links() }}
        </div>
    </div>
</x-app-layout>
