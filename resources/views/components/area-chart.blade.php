@props(['points', 'label', 'prefix' => '₱', 'tone' => 'dark', 'height' => 'h-48'])

@php
    $raw = max(array_column($points, 'value') ?: [0]);
    $max = 1;
    if ($raw > 0) {
        $magnitude = 10 ** floor(log10($raw));
        foreach ([1, 2, 2.5, 5, 10] as $step) {
            if ($step * $magnitude >= $raw) {
                $max = $step * $magnitude;
                break;
            }
        }
    }

    $count = count($points);
    $coords = collect($points)->values()->map(fn ($p, $i) => [
        round($count > 1 ? $i / ($count - 1) * 100 : 50, 3),
        round(100 - $p['value'] / $max * 100, 3),
    ]);
    $line = $coords->map(fn ($c, $i) => ($i ? 'L' : 'M').$c[0].' '.$c[1])->implode(' ');
    $area = $line.' L100 100 L0 100 Z';
    $gradient = 'area-'.uniqid();

    $hero = $tone === 'hero';
    $stroke = $hero ? '#FFFFFF' : '#E8692A';
    $fmt = fn ($v) => $prefix.number_format($v, $v >= 100 ? 0 : 2);
@endphp

<figure x-data="areaChart({ points: @js(array_values($points)), max: {{ $max }}, prefix: @js($prefix) })" {{ $attributes->merge(['class' => 'relative']) }}>
    <div x-ref="plot" class="relative {{ $height }} cursor-crosshair outline-none"
         tabindex="0" role="img" aria-label="{{ $label }}"
         @mousemove="track($event)" @mouseleave="index = null"
         @focus="step(0)" @blur="index = null"
         @keydown.left.prevent="step(-1)" @keydown.right.prevent="step(1)">
        @foreach ([0, 50] as $pct)
            <div class="absolute inset-x-0 border-t border-dashed {{ $hero ? 'border-white/25' : 'border-white/10' }}" style="top: {{ $pct }}%"></div>
            <span class="absolute right-0 -translate-y-full pb-0.5 text-[10px] {{ $hero ? 'text-white/75' : 'text-neutral-500' }}" style="top: {{ $pct }}%">{{ $fmt($max * (100 - $pct) / 100) }}</span>
        @endforeach
        <div class="absolute inset-x-0 bottom-0 border-t {{ $hero ? 'border-white/40' : 'border-white/15' }}"></div>

        <svg class="absolute inset-0 h-full w-full overflow-visible" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
            <defs>
                <linearGradient id="{{ $gradient }}" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="{{ $stroke }}" stop-opacity="{{ $hero ? 0.4 : 0.3 }}" />
                    <stop offset="100%" stop-color="{{ $stroke }}" stop-opacity="0" />
                </linearGradient>
            </defs>
            <path d="{{ $area }}" fill="url(#{{ $gradient }})" />
            <path d="{{ $line }}" fill="none" stroke="{{ $stroke }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
        </svg>

        <template x-if="point">
            <div>
                <div class="pointer-events-none absolute bottom-0 top-0 w-px {{ $hero ? 'bg-white/70' : 'bg-white/30' }}" :style="`left: ${left}%`"></div>
                <div class="pointer-events-none absolute h-2.5 w-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full {{ $hero ? 'bg-white ring-2 ring-brand-orange' : 'bg-brand-orange ring-2 ring-neutral-900' }}" :style="`left: ${left}%; top: ${top}%`"></div>
                <div class="pointer-events-none absolute top-1 z-10 -translate-x-1/2 whitespace-nowrap rounded-lg border border-white/10 bg-neutral-950/95 px-2.5 py-1.5 text-xs shadow-lg"
                     :style="`left: clamp(3.5rem, ${left}%, calc(100% - 3.5rem))`">
                    <p class="text-neutral-400" x-text="point.label"></p>
                    <p class="font-mono font-semibold text-white" x-text="value"></p>
                </div>
            </div>
        </template>
    </div>

    @if ($count)
        <div class="mt-2 flex justify-between text-[10px] {{ $hero ? 'text-white/75' : 'text-neutral-500' }}" aria-hidden="true">
            <span>{{ $points[0]['label'] }}</span>
            <span>{{ $points[intdiv($count - 1, 2)]['label'] }}</span>
            <span>{{ $points[$count - 1]['label'] }}</span>
        </div>
    @endif

    <table class="sr-only">
        <caption>{{ $label }}</caption>
        <tbody>
            @foreach ($points as $p)
                <tr><th scope="row">{{ $p['label'] }}</th><td>{{ $prefix }}{{ number_format($p['value'], 2) }}</td></tr>
            @endforeach
        </tbody>
    </table>
</figure>
