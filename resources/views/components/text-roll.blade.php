@props(['text'])

{{-- Letters are emitted with no whitespace between them; any gap would render as a visible space. --}}
<span x-data="textRoll()" {{ $attributes->merge(['class' => 'inline-block overflow-clip whitespace-nowrap']) }}>@foreach (mb_str_split($text) as $letter)@php($char = $letter === ' ' ? "\u{00A0}" : $letter)<span class="relative inline-block" aria-hidden="true"><span data-roll-out class="absolute inset-0 inline-block">{{ $char }}</span><span data-roll-in class="absolute inset-0 inline-block -translate-y-[110%]">{{ $char }}</span><span class="invisible">{{ $char }}</span></span>@endforeach<span class="sr-only">{{ $text }}</span></span>
