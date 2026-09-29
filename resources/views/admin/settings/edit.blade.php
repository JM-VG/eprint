<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Settings') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data"
                  class="bg-white shadow-sm sm:rounded-lg p-6 space-y-6">
                @csrf
                @method('PUT')

                <section class="space-y-4">
                    <h3 class="font-medium text-gray-900">{{ __('Commission') }}</h3>
                    <div>
                        <x-input-label for="commission_rate" :value="__('Commission rate (%)')" />
                        <x-text-input id="commission_rate" name="commission_rate" type="number" step="0.01" min="0" max="100"
                                      class="mt-1 block w-40" :value="old('commission_rate', $settings['commission_rate'])" required />
                        <p class="mt-1 text-xs text-gray-500">{{ __('Applied to new sales only; past sales keep their rate.') }}</p>
                        <x-input-error :messages="$errors->get('commission_rate')" class="mt-2" />
                    </div>
                </section>

                <section class="space-y-4">
                    <h3 class="font-medium text-gray-900">{{ __('GCash') }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="gcash_name" :value="__('Account name')" />
                            <x-text-input id="gcash_name" name="gcash_name" class="mt-1 block w-full" :value="old('gcash_name', $settings['gcash_name'])" />
                            <x-input-error :messages="$errors->get('gcash_name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="gcash_number" :value="__('Mobile number')" />
                            <x-text-input id="gcash_number" name="gcash_number" class="mt-1 block w-full" :value="old('gcash_number', $settings['gcash_number'])" placeholder="09XXXXXXXXX" />
                            <x-input-error :messages="$errors->get('gcash_number')" class="mt-2" />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="gcash_qr" :value="__('QR code image (PNG or JPG, max 2 MB)')" />
                        @if ($gcashQrUrl)
                            <img src="{{ $gcashQrUrl }}" alt="{{ __('Current GCash QR code') }}" class="mt-2 h-40 w-40 object-contain border rounded">
                        @endif
                        <input id="gcash_qr" name="gcash_qr" type="file" accept="image/png,image/jpeg" class="mt-2 block text-sm">
                        <x-input-error :messages="$errors->get('gcash_qr')" class="mt-2" />
                    </div>
                </section>

                <section class="space-y-4">
                    <h3 class="font-medium text-gray-900">{{ __('Files and accounts') }}</h3>
                    <div>
                        <x-input-label for="retention_days" :value="__('Keep uploaded files for (days)')" />
                        <x-text-input id="retention_days" name="retention_days" type="number" min="1" max="365"
                                      class="mt-1 block w-40" :value="old('retention_days', $settings['retention_days'])" required />
                        <x-input-error :messages="$errors->get('retention_days')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="student_id_pattern" :value="__('Student ID pattern (regular expression)')" />
                        <x-text-input id="student_id_pattern" name="student_id_pattern" class="mt-1 block w-full font-mono"
                                      :value="old('student_id_pattern', $settings['student_id_pattern'])" required />
                        <p class="mt-1 text-xs text-gray-500">{{ __('Default: ^[A-Z]{3}\d{4}-\d{5}$ (example MMC2024-00123).') }}</p>
                        <x-input-error :messages="$errors->get('student_id_pattern')" class="mt-2" />
                    </div>
                </section>

                <div class="flex justify-end">
                    <x-primary-button>{{ __('Save settings') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
