@props(['percent', 'label'])

@php
    $pct = max(0, min(100, (float) $percent));
@endphp

<div {{ $attributes->merge(['class' => 'relative']) }} role="img" aria-label="{{ $label }}: {{ round($pct) }}%">
    <svg viewBox="0 0 100 56" class="w-full" aria-hidden="true">
        <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="9" stroke-linecap="round" />
        @if ($pct > 0)
            <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="#FFD900" stroke-width="9" stroke-linecap="round"
                  pathLength="100" stroke-dasharray="{{ $pct }} 100" />
        @endif
    </svg>
    <div class="absolute inset-x-0 bottom-0 text-center">
        <span class="text-3xl font-semibold text-white">{{ round($pct) }}</span><span class="text-sm text-neutral-500">%</span>
    </div>
</div>
