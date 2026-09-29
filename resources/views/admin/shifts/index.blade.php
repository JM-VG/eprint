<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Shift History') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('admin.shifts.index') }}" class="bg-white shadow-sm sm:rounded-lg p-4 grid grid-cols-1 sm:grid-cols-5 gap-3 items-end text-sm">
                <div>
                    <label for="user_id" class="block text-gray-600">{{ __('Staff') }}</label>
                    <select id="user_id" name="user_id" class="mt-1 w-full rounded-md border-gray-300">
                        <option value="">{{ __('All staff') }}</option>
                        @foreach ($staff as $member)
                            <option value="{{ $member->id }}" @selected((int) ($filters['user_id'] ?? 0) === $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="location_id" class="block text-gray-600">{{ __('Location') }}</label>
                    <select id="location_id" name="location_id" class="mt-1 w-full rounded-md border-gray-300">
                        <option value="">{{ __('All locations') }}</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((int) ($filters['location_id'] ?? 0) === $location->id)>{{ $location->name }}</option>
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
                    <a href="{{ route('admin.shifts.index') }}" class="px-3 py-2 text-gray-600">{{ __('Reset') }}</a>
                </div>
                @if ($errors->any())
                    <div class="sm:col-span-5 text-red-600">{{ $errors->first() }}</div>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('Staff') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Location') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Opened') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Closed') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Duration') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Close reason') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($shifts as $shift)
                            <tr>
                                <td class="px-4 py-3">{{ $shift->user->name }}</td>
                                <td class="px-4 py-3">{{ $shift->location->name }}</td>
                                <td class="px-4 py-3">{{ $shift->opened_at->format('M j, Y g:i A') }}</td>
                                <td class="px-4 py-3">{{ $shift->closed_at?->format('M j, Y g:i A') ?? __('Ongoing') }}</td>
                                <td class="px-4 py-3">{{ \App\Services\ShiftHistoryService::duration($shift) }}</td>
                                <td class="px-4 py-3">{{ $shift->close_reason ? ucfirst($shift->close_reason->value) : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('No shifts match these filters.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $shifts->links() }}
        </div>
    </div>
</x-app-layout>
