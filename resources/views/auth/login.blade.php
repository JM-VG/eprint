<x-auth-layout :title="__('Welcome Back')">
    <x-slot name="subtitle">
        {{ __("Don't have an account yet?") }}
        <a href="{{ route('register') }}" class="font-medium text-brand-yellow hover:text-brand-300">{{ __('Sign up') }}</a>
    </x-slot>

    @if (session('status'))
        <p class="mb-4 rounded-lg border border-emerald-400/20 bg-emerald-400/10 px-3 py-2 text-xs text-emerald-300">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-3">
        @csrf

        <x-auth-input name="login" icon="user" :label="__('Student ID or Username')" required autofocus autocomplete="username" />
        <x-auth-input name="password" icon="lock" type="password" :label="__('Password')" required autocomplete="current-password" />

        <div class="flex items-center justify-between pt-1 text-xs">
            <label for="remember_me" class="inline-flex items-center gap-2 text-neutral-400">
                <input id="remember_me" type="checkbox" name="remember" class="rounded border-white/20 bg-neutral-950 text-brand-orange focus:ring-brand-yellow focus:ring-offset-neutral-900">
                {{ __('Remember me') }}
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-neutral-400 hover:text-brand-yellow">{{ __('Forgot password?') }}</a>
            @endif
        </div>

        <button type="submit" class="mt-2 w-full rounded-lg bg-brand-orange py-2 text-sm font-medium text-white transition-colors hover:bg-[#d35d22] focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-yellow focus-visible:ring-offset-2 focus-visible:ring-offset-neutral-900">
            {{ __('Log in') }}
        </button>
    </form>
</x-auth-layout>
