<div class="pointer-events-none fixed inset-x-0 bottom-0 z-40 flex justify-center">
    <div class="no-scrollbar max-w-full overflow-x-auto px-2 pt-14 pb-3">
        <nav x-data="dock()"
             @mousemove="move($event)"
             @mouseleave="leave()"
             aria-label="Main menu"
             class="pointer-events-auto mx-auto flex h-14 w-fit items-end gap-3 rounded-2xl border border-brand-200 bg-white/80 px-3 pb-2 shadow-lg shadow-brand-orange/10 backdrop-blur">
            @foreach ($sections as $heading => $items)
                @if (! $loop->first)
                    <span class="mb-1 h-8 w-px shrink-0 self-end bg-brand-200" aria-hidden="true"></span>
                @endif

                @foreach ($items as $item)
                    @php
                        $tag = $item['url'] ? 'a' : 'span';
                    @endphp
                    <{{ $tag }}
                        data-dock-item
                        data-menu="{{ $item['key'] }}"
                        x-data="{ hover: false }"
                        @mouseenter="hover = true" @mouseleave="hover = false"
                        @focus="hover = true" @blur="hover = false"
                        @if ($item['url'])
                            href="{{ $item['url'] }}"
                            @if ($item['active']) aria-current="page" @endif
                        @else
                            tabindex="0" aria-disabled="true"
                        @endif
                        aria-label="{{ $item['label'] }}"
                        style="width: 40px; height: 40px;"
                        class="relative flex shrink-0 items-center justify-center rounded-full outline-none focus-visible:ring-2 focus-visible:ring-brand-orange {{ $item['url'] ? '' : 'cursor-not-allowed opacity-40' }}">
                        @if ($item['active'])
                            <x-glow-effect mode="rotate" blur="soft" :scale="1.15" :duration="4" class="rounded-full" />
                        @endif

                        <span class="relative flex h-full w-full items-center justify-center rounded-full transition-colors {{ $item['active'] ? 'bg-brand-yellow text-neutral-900' : 'bg-brand-100 text-neutral-600 hover:bg-brand-200' }}">
                            <x-icon :name="$item['key']" class="h-1/2 w-1/2" />
                        </span>

                        @if ($item['key'] === 'queue')
                            <span data-queue-badge data-endpoint="{{ route('api.queue-count') }}" hidden
                                  @if ($realtimeUrl = \App\Realtime\PublicProjections::url(\App\Realtime\PublicProjections::QUEUE)) data-realtime-url="{{ $realtimeUrl }}" @endif
                                  @if ($stationId = app(\App\Services\StationService::class)->current($user)?->location_id) data-location-id="{{ $stationId }}" @endif
                                  aria-label="{{ __('pending jobs') }}"
                                  class="absolute -right-1 -top-1 min-w-5 rounded-full bg-brand-orange px-1.5 text-center text-xs text-white"></span>
                        @endif

                        <span x-show="hover" x-cloak role="tooltip"
                              x-transition:enter="transition duration-200" x-transition:enter-start="opacity-0 translate-y-2.5"
                              x-transition:leave="transition duration-200" x-transition:leave-end="opacity-0 translate-y-2.5"
                              class="absolute -top-9 left-1/2 -translate-x-1/2 whitespace-pre rounded-md border border-brand-200 bg-brand-100 px-2 py-0.5 text-xs text-neutral-700">{{ $item['label'] }}{{ $item['url'] ? '' : ' (soon)' }}</span>
                    </{{ $tag }}>
                @endforeach
            @endforeach
        </nav>
    </div>
</div>
