<x-guest-layout>
    <h1 class="text-lg font-semibold text-gray-900">{{ __('Student Sign Up') }}</h1>
    <p class="mt-1 mb-4 text-sm text-gray-600">{{ __('An admin reviews every new account before you can submit print jobs.') }}</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Student ID -->
        <div>
            <x-input-label for="student_id" :value="__('Student ID')" />
            <x-text-input id="student_id" class="block mt-1 w-full uppercase" type="text" name="student_id" :value="old('student_id')" required autofocus autocomplete="username" placeholder="MMC2024-00123" />
            <p class="mt-1 text-xs text-gray-500">{{ __('Example: MMC2024-00123') }}</p>
            <x-input-error :messages="$errors->get('student_id')" class="mt-2" />
        </div>

        <!-- First Name -->
        <div class="mt-4">
            <x-input-label for="first_name" :value="__('First Name')" />
            <x-text-input id="first_name" class="block mt-1 w-full" type="text" name="first_name" :value="old('first_name')" required autocomplete="given-name" />
            <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
        </div>

        <!-- Last Name -->
        <div class="mt-4">
            <x-input-label for="last_name" :value="__('Last Name')" />
            <x-text-input id="last_name" class="block mt-1 w-full" type="text" name="last_name" :value="old('last_name')" required autocomplete="family-name" />
            <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Sign Up') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
