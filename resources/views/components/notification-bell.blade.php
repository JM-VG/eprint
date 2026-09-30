@php($unread = auth()->user()?->unreadNotifications()->count() ?? 0)

<a href="{{ route('notifications.index') }}" class="relative inline-flex items-center p-2 text-neutral-400 hover:text-white"
   aria-label="{{ __('Notifications') }}">
    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3C7.7 6.2 6 8.4 6 11v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
    </svg>
    <span data-notification-bell data-endpoint="{{ route('api.notifications.count') }}" data-recent-endpoint="{{ route('api.notifications.recent') }}" @if ($unread === 0) hidden @endif
          class="absolute -top-0.5 -right-0.5 min-w-5 px-1 rounded-full bg-brand-orange text-white text-xs text-center">{{ $unread }}</span>
</a>
