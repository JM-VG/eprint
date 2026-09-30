<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Notifications') }}</h2>
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="text-sm text-indigo-600 hover:underline">{{ __('Mark all as read') }}</button>
            </form>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <ul class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100">
                @forelse ($notifications as $notification)
                    <li class="flex items-start justify-between gap-4 px-4 py-3 {{ $notification->read_at ? '' : 'bg-indigo-50' }}">
                        <div class="text-sm">
                            <p class="text-gray-900">{{ $notification->data['message'] ?? __('Notification') }}</p>
                            <p class="text-gray-500">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                            @csrf
                            <button class="text-sm text-indigo-600 hover:underline whitespace-nowrap">
                                {{ ! empty($notification->data['url']) ? __('Open') : ($notification->read_at ? __('Read') : __('Mark read')) }}
                            </button>
                        </form>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-gray-500 text-sm">{{ __('No notifications yet.') }}</li>
                @endforelse
            </ul>

            {{ $notifications->links() }}
        </div>
    </div>
</x-app-layout>
