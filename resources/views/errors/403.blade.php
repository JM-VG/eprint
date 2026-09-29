<x-guest-layout>
    <h1 class="text-lg font-semibold text-gray-900">{{ __('Access denied') }}</h1>

    <p class="mt-2 text-sm text-gray-600">
        {{ __('You do not have access to this page.') }}
    </p>

    <div class="mt-4 flex justify-end">
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="underline text-sm text-gray-600 hover:text-gray-900">
            {{ auth()->check() ? __('Back to dashboard') : __('Go to login') }}
        </a>
    </div>
</x-guest-layout>
