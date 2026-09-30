@props([
    'colors' => ['#E8692A', '#FFD900', '#FFE670', '#FFF3B9'],
    'mode' => 'rotate',
    'blur' => 'medium',
    'scale' => 1,
    'duration' => 5,
])

@php
    $blurPx = is_numeric($blur) ? (float) $blur : [
        'softest' => 2, 'soft' => 4, 'medium' => 12, 'strong' => 16,
        'stronger' => 24, 'strongest' => 40, 'none' => 0,
    ][$blur] ?? 12;

    $list = implode(', ', $colors);
    $background = match ($mode) {
        'pulse', 'breathe' => "radial-gradient(circle at 50% 50%, {$colors[0]} 0%, transparent 100%)",
        'flowHorizontal', 'static' => "linear-gradient(to right, {$list})",
        default => "conic-gradient(from var(--glow-angle) at 50% 50%, {$list}, {$colors[0]})",
    };
@endphp

<div aria-hidden="true"
     {{ $attributes->merge(['class' => "glow-effect glow-effect--{$mode} pointer-events-none absolute inset-0 h-full w-full"]) }}
     style="--glow-scale: {{ $scale }}; --glow-duration: {{ $duration }}s; background: {{ $background }}; filter: blur({{ $blurPx }}px);"></div>
