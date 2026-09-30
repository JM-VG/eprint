<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Claim') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('staff.claim') }}" class="bg-white shadow-sm sm:rounded-lg p-4 flex gap-3 items-end">
                <div class="flex-1">
                    <x-input-label for="code" :value="__('Claim code (type or scan the QR)')" />
                    <x-text-input id="code" name="code" class="mt-1 block w-full font-mono uppercase" :value="$code" placeholder="EP-XXXX" autofocus autocomplete="off" />
                </div>
                <x-primary-button>{{ __('Find') }}</x-primary-button>
            </form>

            @if ($code !== '' && ! $job)
                <div class="bg-white shadow-sm sm:rounded-lg p-4 text-sm text-red-700">{{ __('No job found with code :code.', ['code' => $code]) }}</div>
            @elseif ($job && $job->status !== \App\Enums\JobStatus::Ready)
                <div class="bg-white shadow-sm sm:rounded-lg p-4 text-sm text-yellow-800">
                    {{ __(':code is :status, not ready for pickup.', ['code' => $job->claim_code, 'status' => $job->status->value]) }}
                </div>
            @elseif ($job)
                <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <dt class="text-gray-500">{{ __('Claim code') }}</dt>
                        <dd class="font-mono font-semibold">{{ $job->claim_code }}</dd>
                        <dt class="text-gray-500">{{ __('Owner') }}</dt>
                        <dd>{{ $job->user->name }} <span class="text-gray-500">{{ $job->user->student_id ?? $job->user->username }}</span></dd>
                        <dt class="text-gray-500">{{ __('Job') }}</dt>
                        <dd>{{ $job->service->name }} — {{ $job->original_name }}</dd>
                        <dt class="text-gray-500">{{ __('Location') }}</dt>
                        <dd>{{ $job->location->name }}</dd>
                    </dl>

                    <p class="text-3xl font-bold text-gray-900">{{ __('Total') }}: ₱{{ $job->final_total ?? $job->estimated_total }}</p>

                    <form method="POST" action="{{ route('staff.claim.store') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="code" value="{{ $job->claim_code }}">

                        <fieldset class="flex gap-4">
                            <legend class="sr-only">{{ __('Payment method') }}</legend>
                            <label class="flex items-center gap-2"><input type="radio" name="payment_method" value="cash" required @checked(old('payment_method', 'cash') === 'cash')> {{ __('Cash') }}</label>
                            <label class="flex items-center gap-2"><input type="radio" name="payment_method" value="gcash" @checked(old('payment_method') === 'gcash')> {{ __('GCash') }}</label>
                        </fieldset>
                        <x-input-error :messages="$errors->get('payment_method')" />

                        <div>
                            <x-input-label for="reference_no" :value="__('GCash reference number')" />
                            <x-text-input id="reference_no" name="reference_no" class="mt-1 block w-full font-mono" :value="old('reference_no')" autocomplete="off" />
                            <x-input-error :messages="$errors->get('reference_no')" class="mt-2" />
                            @if ($gcash['gcash_number'])
                                <p class="mt-1 text-xs text-gray-500">{{ __('Pay to :name, :number', ['name' => $gcash['gcash_name'], 'number' => $gcash['gcash_number']]) }}</p>
                            @endif
                            @if ($gcashQrUrl)
                                <img src="{{ $gcashQrUrl }}" alt="{{ __('GCash QR code') }}" class="mt-2 h-32 w-32 object-contain border rounded">
                            @endif
                        </div>

                        <x-primary-button>{{ __('Confirm payment and release') }}</x-primary-button>
                    </form>
                </section>
            @endif

            <x-input-error :messages="$errors->get('code')" />
        </div>
    </div>
</x-app-layout>
