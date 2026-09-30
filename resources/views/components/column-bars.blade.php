@props(['items', 'height' => 'h-40'])

{{-- items: list of ['label' => string, 'value' => float, 'display' => string, 'fill' => tailwind bg class] --}}
@php
    $total = array_sum(array_column($items, 'value'));
@endphp

<div {{ $attributes->merge(['class' => 'grid gap-2']) }} style="grid-template-columns: repeat({{ count($items) }}, minmax(0, 1fr));">
    @foreach ($items as $item)
        @php
            $share = $total > 0 ? $item['value'] / $total * 100 : 0;
        @endphp
        <div class="min-w-0" title="{{ $item['label'] }}: {{ $item['display'] }} ({{ round($share) }}%)">
            <div class="relative {{ $height }} overflow-hidden rounded-xl bg-white/5">
                <div class="absolute inset-x-0 bottom-0 rounded-xl {{ $item['fill'] }} transition-[height] duration-700"
                     style="height: {{ $item['value'] > 0 ? max($share, 14) : 0 }}%">
                    <span class="absolute left-2 top-2 text-xs font-semibold text-neutral-950">{{ round($share) }}%</span>
                </div>
                @if ($item['value'] <= 0)
                    <span class="absolute bottom-2 left-2 text-xs text-neutral-500">0%</span>
                @endif
            </div>
            <p class="mt-2 truncate text-xs text-neutral-400">{{ $item['label'] }}</p>
            <p class="truncate font-mono text-sm font-medium text-white">{{ $item['display'] }}</p>
        </div>
    @endforeach
</div>
