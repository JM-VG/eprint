<x-guest-layout>
    <h1 class="text-lg font-semibold text-gray-900">{{ __('Sign up received') }}</h1>

    <p class="mt-2 text-sm text-gray-600">
        {{ __('Your account is waiting for approval. An admin will review your Student ID; once approved you can log in and submit print jobs.') }}
    </p>

    <p class="mt-2 text-sm text-gray-600">
        {{ __('If it takes too long, visit the printing room with your school ID.') }}
    </p>

    <div class="mt-4 flex justify-end">
        <a href="{{ route('login') }}" class="underline text-sm text-gray-600 hover:text-gray-900">
            {{ __('Back to login') }}
        </a>
    </div>
</x-guest-layout>
