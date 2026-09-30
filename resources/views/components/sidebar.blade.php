<aside class="bg-white border-b md:border-b-0 md:border-r border-gray-200 md:w-64 md:shrink-0">
    <nav class="px-4 py-4 md:py-6 space-y-4" aria-label="Main menu">
        @foreach ($sections as $heading => $items)
            <div>
                <p class="px-3 mb-1 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ $heading }}</p>
                <ul class="flex flex-wrap gap-1 md:block md:space-y-1">
                    @foreach ($items as $item)
                        <li>
                            @if ($item['url'])
                                <a href="{{ $item['url'] }}"
                                   data-menu="{{ $item['key'] }}"
                                   @if ($item['active']) aria-current="page" @endif
                                   class="flex items-center justify-between gap-2 px-3 py-2 rounded-md text-sm font-medium {{ $item['active'] ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100' }}">
                                    <span>{{ $item['label'] }}</span>
                                    @if ($item['key'] === 'queue')
                                        <span data-queue-badge data-endpoint="{{ route('api.queue-count') }}" hidden
                                              aria-label="{{ __('pending jobs') }}"
                                              class="min-w-5 px-1.5 rounded-full bg-red-600 text-white text-xs text-center"></span>
                                    @endif
                                </a>
                            @else
                                <span data-menu="{{ $item['key'] }}"
                                      aria-disabled="true"
                                      title="Coming soon"
                                      class="block px-3 py-2 rounded-md text-sm font-medium text-gray-400 cursor-not-allowed">
                                    {{ $item['label'] }}
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>
</aside>
