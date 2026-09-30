<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Announcements') }}</h2>
            <div x-data="morphingPopover({ openOnLoad: @js($errors->hasAny(['title', 'body'])) })"
                 @click.outside="close()"
                 @keydown.escape.window="close()"
                 class="relative z-30 flex items-center justify-end">
                <button type="button" x-ref="trigger" @click="open()"
                        :aria-expanded="isOpen" aria-controls="announcement-popover"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand-orange px-3 text-sm font-medium text-white hover:bg-brand-orange/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-yellow">
                    <x-icon name="plus" class="h-4 w-4" />
                    {{ __('New announcement') }}
                </button>

                <div x-ref="panel" x-show="isOpen" x-cloak
                     id="announcement-popover" role="dialog" aria-modal="true" aria-label="{{ __('New announcement') }}"
                     class="absolute right-0 top-0 w-[min(364px,calc(100vw-2rem))] overflow-hidden rounded-xl border border-brand-200 bg-white shadow-[0_9px_9px_0px_rgba(0,0,0,0.01),_0_2px_5px_0px_rgba(0,0,0,0.06)]">
                    <form x-ref="content" method="POST" action="{{ route('admin.announcements.store') }}"
                          class="flex w-[min(364px,calc(100vw-2rem))] flex-col">
                        @csrf
                        <input x-ref="autofocus" name="title" maxlength="150" required
                               value="{{ old('title') }}" placeholder="{{ __('Announcement title') }}"
                               class="border-0 border-b border-brand-100 bg-transparent px-4 pb-2 pt-3 text-sm font-semibold text-neutral-900 placeholder:text-neutral-400 focus:border-brand-yellow focus:ring-0">
                        <textarea name="body" rows="6" maxlength="2000" required
                                  placeholder="{{ __('Write your message…') }}"
                                  class="h-[140px] w-full resize-none border-0 bg-transparent px-4 py-3 text-sm text-neutral-800 placeholder:text-neutral-400 focus:ring-0">{{ old('body') }}</textarea>
                        @if ($errors->hasAny(['title', 'body']))
                            <div class="px-4">
                                <x-input-error :messages="array_merge($errors->get('title'), $errors->get('body'))" />
                            </div>
                        @endif
                        <div class="flex items-center justify-between py-3 pl-2 pr-4">
                            <button type="button" @click="close()" aria-label="{{ __('Close popover') }}"
                                    class="flex items-center rounded-lg px-2 py-1 text-sm text-neutral-900 hover:bg-brand-100">
                                <x-icon name="arrow-left" class="h-4 w-4" />
                            </button>
                            <div class="flex items-center gap-3">
                                <a href="{{ route('admin.announcements.create') }}" class="text-xs text-neutral-500 hover:text-brand-orange">{{ __('Schedule…') }}</a>
                                <button type="submit"
                                        class="flex h-8 items-center rounded-lg bg-brand-yellow px-3 text-sm font-medium text-neutral-900 transition-colors hover:bg-brand-300 focus-visible:ring-2 focus-visible:ring-brand-orange active:scale-[0.98]">
                                    {{ __('Publish') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('Title') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Shown') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('By') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($announcements as $announcement)
                            <tr>
                                <td class="px-4 py-3">{{ $announcement->title }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ $announcement->starts_at?->format('M j, Y g:i A') ?? __('now') }}
                                    –
                                    {{ $announcement->ends_at?->format('M j, Y g:i A') ?? __('until removed') }}
                                </td>
                                <td class="px-4 py-3">{{ $announcement->creator?->name }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-3">
                                        <a href="{{ route('admin.announcements.edit', $announcement) }}" class="text-indigo-600 hover:underline">{{ __('Edit') }}</a>
                                        <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" onsubmit="return confirm('Delete this announcement?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ __('No announcements yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $announcements->links() }}
        </div>
    </div>
</x-app-layout>
