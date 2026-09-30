@props(['title', 'subtitle' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-neutral-950">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title }} · {{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600|montserrat:800i,900i&display=swap" rel="stylesheet" />
        <link rel="icon" type="image/png" href="{{ asset('images/eprint-logo-64.png') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-neutral-950 text-neutral-100">
        <x-availability-banner />
        <x-announcement-banner />

        <div class="relative flex min-h-screen flex-col items-center justify-center gap-4 overflow-hidden px-4 pb-12 pt-20 xl:pt-12">
            {{-- Spotlight under a grid the same color as the page, so the glow shows through the gaps --}}
            <div x-data="spotlight({ size: 360 })" aria-hidden="true"
                 :class="visible ? 'opacity-100' : 'opacity-0'"
                 class="pointer-events-none absolute left-0 top-0 h-[360px] w-[360px] rounded-full bg-[radial-gradient(circle_at_center,var(--tw-gradient-stops),transparent_80%)] from-brand-orange via-brand-yellow/70 to-brand-orange/0 opacity-0 blur-xl transition-opacity duration-200"></div>
            <svg class="pointer-events-none absolute inset-0 h-full w-full" aria-hidden="true">
                <defs>
                    <pattern id="auth-grid" width="16" height="16" patternUnits="userSpaceOnUse">
                        <path d="M0 8H8M8 8V0M8 8H16M8 8V16" stroke="#0a0a0a" stroke-opacity="0.85" />
                        <rect x="6" y="6" width="4" height="4" fill="#0a0a0a" fill-opacity="0.6" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#auth-grid)" />
            </svg>

            {{-- Circuit-board backdrop --}}
            <div class="pointer-events-none absolute inset-0 hidden md:block" aria-hidden="true">
                <svg class="absolute inset-0 h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none" fill="none">
                    @foreach ([
                        'M 11 15 H 28 L 31 20 V 42 L 42 48',
                        'M 89 15 H 72 L 69 20 V 42 L 58 48',
                        'M 11 85 H 28 L 31 80 V 58 L 42 52',
                        'M 89 85 H 72 L 69 80 V 58 L 58 52',
                    ] as $path)
                        <path d="{{ $path }}" stroke="rgba(255,230,112,0.16)" stroke-width="1" vector-effect="non-scaling-stroke" />
                    @endforeach
                </svg>

                @foreach ([['left-[3%] top-[15%]', 'left-[11%] top-[15%]'], ['right-[3%] top-[15%]', 'right-[11%] top-[15%]'], ['left-[3%] top-[85%]', 'left-[11%] top-[85%]'], ['right-[3%] top-[85%]', 'right-[11%] top-[85%]']] as [$chip, $dot])
                    <div class="absolute {{ $chip }} -translate-y-1/2 flex h-7 w-[7%] min-w-12 flex-col justify-between rounded-md border border-white/10 bg-neutral-900 px-1.5 py-1">
                        <div class="flex justify-between">@for ($i = 0; $i < 5; $i++)<span class="h-0.5 w-0.5 rounded-full bg-brand-200/40"></span>@endfor</div>
                        <div class="flex justify-between">@for ($i = 0; $i < 5; $i++)<span class="h-0.5 w-0.5 rounded-full bg-brand-200/40"></span>@endfor</div>
                    </div>
                    <span class="absolute {{ $dot }} h-1.5 w-1.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-yellow shadow-[0_0_8px_2px_rgba(255,217,0,0.6)]"></span>
                @endforeach
            </div>

            {{-- Partner logos --}}
            <div class="absolute left-3 top-3 z-20 flex items-center gap-3 sm:left-6 sm:top-5 sm:gap-4">
                <img src="{{ asset('images/minsu-ccs-logo.png') }}" alt="MinSU College of Computer Studies" title="MinSU College of Computer Studies"
                     width="192" height="188" class="h-14 w-14 rounded-full object-contain ring-1 ring-white/20 sm:h-20 sm:w-20">
                <img src="{{ asset('images/minsu-it-logo.png') }}" alt="MinSU Information Technology" title="MinSU Information Technology"
                     width="192" height="192" class="h-14 w-14 rounded-full bg-white object-contain ring-1 ring-white/20 sm:h-20 sm:w-20">
            </div>

            {{-- 3D logo coin: above the card on small screens, beside it on wide ones --}}
            <canvas data-logo-coin
                    data-model="{{ asset('models/eprint-logo.stl') }}"
                    data-texture="{{ asset('images/eprint-logo-texture.jpg') }}"
                    aria-hidden="true"
                    class="pointer-events-none relative z-0 h-40 w-40 shrink-0 opacity-0 transition-opacity duration-700 [&.is-ready]:opacity-100 sm:h-52 sm:w-52 xl:absolute xl:right-[-3vw] xl:top-1/2 xl:h-[min(42vw,640px)] xl:w-[min(42vw,640px)] xl:-translate-y-1/2"></canvas>

            <div class="relative z-10 w-full max-w-sm">
                <x-flash-messages />

                <div class="rounded-2xl border border-white/10 bg-neutral-900/90 px-5 py-7 shadow-2xl shadow-black/60 backdrop-blur sm:px-8 sm:py-8">
                    <div class="hidden items-center justify-center gap-3 xl:flex">
                        <span class="h-px w-14 border-t border-dashed border-white/15"></span>
                        <a href="/" class="relative flex h-14 w-14 items-center justify-center">
                            <x-glow-effect mode="rotate" blur="soft" :scale="0.95" :duration="6" class="rounded-full opacity-70" />
                            <x-application-logo class="relative h-full w-full rounded-full ring-1 ring-white/10" />
                        </a>
                        <span class="h-px w-14 border-t border-dashed border-white/15"></span>
                    </div>

                    <p class="text-center xl:mt-3">
                        <x-text-roll text="e-Print" class="px-1 font-wordmark text-4xl font-black italic leading-tight tracking-[-0.04em] text-brand-green" />
                    </p>

                    <h1 class="mt-3 text-center text-xl font-semibold text-white">{{ $title }}</h1>
                    @if ($subtitle)
                        <p class="mt-2 text-center text-xs text-neutral-400">{{ $subtitle }}</p>
                    @endif

                    <div class="mt-6">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
