<x-auth-layout :title="__('Student Sign Up')">
    <x-slot name="subtitle">
        {{ __('Already have an account?') }}
        <a href="{{ route('login') }}" class="font-medium text-brand-yellow hover:text-brand-300">{{ __('Log in') }}</a>
    </x-slot>

    <p class="mb-4 rounded-lg border border-brand-yellow/20 bg-brand-yellow/5 px-3 py-2 text-xs text-brand-200">
        {{ __('An admin reviews every new account before you can submit print jobs.') }}
    </p>

    <form method="POST" action="{{ route('register') }}" class="space-y-3">
        @csrf

        <div>
            <x-auth-input name="student_id" icon="id-card" :label="__('Student ID (e.g. MMC2024-00123)')" class="uppercase placeholder:normal-case" required autofocus autocomplete="username" />
        </div>

        <div class="grid grid-cols-2 gap-3">
            <x-auth-input name="first_name" icon="user" :label="__('First Name')" required autocomplete="given-name" />
            <x-auth-input name="last_name" icon="user" :label="__('Last Name')" required autocomplete="family-name" />
        </div>

        <x-auth-input name="password" icon="lock" type="password" :label="__('Password')" required autocomplete="new-password" />
        <x-auth-input name="password_confirmation" icon="lock" type="password" :label="__('Confirm Password')" required autocomplete="new-password" />

        <button type="submit" class="mt-2 w-full rounded-lg bg-brand-orange py-2 text-sm font-medium text-white transition-colors hover:bg-[#d35d22] focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-yellow focus-visible:ring-offset-2 focus-visible:ring-offset-neutral-900">
            {{ __('Sign Up') }}
        </button>
    </form>
</x-auth-layout>
