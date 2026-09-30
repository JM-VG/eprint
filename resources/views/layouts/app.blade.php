<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if (in_array(auth()->user()?->role, [\App\Enums\Role::Staff, \App\Enums\Role::Admin], true))
            <meta name="eprint-heartbeat" content="{{ route('api.heartbeat') }}">
        @endif

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600|montserrat:800i,900i&display=swap" rel="stylesheet" />
        <link rel="icon" type="image/png" href="{{ asset('images/eprint-logo-64.png') }}">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased {{ $dark ? 'bg-neutral-950' : 'bg-gray-100' }}">
        <div class="min-h-screen {{ $dark ? 'bg-neutral-950 text-neutral-100' : 'bg-gray-100' }}">
            <x-availability-banner />
            <x-announcement-banner />

            @include('layouts.navigation')

            <div class="pb-32">
                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <x-flash-messages />

                <!-- Page Content -->
                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>

        <x-sidebar />
        <x-notification-toasts />
    </body>
</html>
