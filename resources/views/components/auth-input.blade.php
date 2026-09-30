@props(['name', 'label', 'icon', 'type' => 'text'])

<div>
    <label for="{{ $name }}" class="sr-only">{{ $label }}</label>
    <div class="relative">
        <x-icon :name="$icon" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-500" />
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" placeholder="{{ $label }}"
               @if ($type !== 'password') value="{{ old($name) }}" @endif
               @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
               {{ $attributes->merge(['class' => 'block w-full rounded-lg border border-white/10 bg-neutral-950/60 py-2 pl-9 pr-3 text-sm text-white placeholder:text-neutral-500 focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow']) }}>
    </div>
    @error($name)
        <p id="{{ $name }}-error" class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
    @enderror
</div>
