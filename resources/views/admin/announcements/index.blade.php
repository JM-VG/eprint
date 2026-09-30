<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Announcements') }}</h2>
            <a href="{{ route('admin.announcements.create') }}" class="px-3 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700">{{ __('New announcement') }}</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('Title') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Shown') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('By') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($announcements as $announcement)
                            <tr>
                                <td class="px-4 py-3">{{ $announcement->title }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ $announcement->starts_at?->format('M j, Y g:i A') ?? __('now') }}
                                    –
                                    {{ $announcement->ends_at?->format('M j, Y g:i A') ?? __('until removed') }}
                                </td>
                                <td class="px-4 py-3">{{ $announcement->creator?->name }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-3">
                                        <a href="{{ route('admin.announcements.edit', $announcement) }}" class="text-indigo-600 hover:underline">{{ __('Edit') }}</a>
                                        <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" onsubmit="return confirm('Delete this announcement?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ __('No announcements yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $announcements->links() }}
        </div>
    </div>
</x-app-layout>
