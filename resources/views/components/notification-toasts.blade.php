<div x-data="toasts()" aria-live="polite" role="status"
     class="pointer-events-none fixed inset-x-4 top-20 z-50 flex flex-col items-end gap-3 sm:inset-x-auto sm:right-6 sm:w-96">
    <template x-for="item in items" :key="item.id">
        <div class="toast-in pointer-events-auto relative w-full overflow-hidden rounded-2xl border border-white/10 bg-neutral-900/95 p-4 text-sm text-neutral-100 shadow-2xl shadow-black/40 backdrop-blur">
            <span class="absolute inset-y-0 left-0 w-1"
                  :class="item.kind === 'job_claimed' ? 'bg-brand-green' : 'bg-brand-yellow'"></span>
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                      :class="item.kind === 'job_claimed' ? 'bg-brand-green/20 text-emerald-300' : 'bg-brand-yellow/15 text-brand-yellow'">
                    <template x-if="item.kind === 'job_claimed'"><x-icon name="check-circle" class="h-5 w-5" /></template>
                    <template x-if="item.kind !== 'job_claimed'"><x-icon name="bell" class="h-5 w-5" /></template>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-white" x-text="item.kind === 'job_claimed' ? '{{ __('Print claimed') }}' : '{{ __('New notification') }}'"></p>
                    <p class="mt-0.5 text-neutral-300" x-text="item.message"></p>
                    <button type="button" x-show="item.url" @click="open(item)"
                            class="mt-2 text-xs font-medium text-brand-yellow hover:text-brand-300">{{ __('View details') }}</button>
                </div>
                <button type="button" @click="dismiss(item.id)" class="rounded-md p-1 text-neutral-500 hover:bg-white/10 hover:text-white" aria-label="{{ __('Dismiss') }}">
                    <x-icon name="x" class="h-4 w-4" />
                </button>
            </div>
        </div>
    </template>
</div>
