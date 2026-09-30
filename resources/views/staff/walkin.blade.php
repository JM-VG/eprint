<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Walk-in Sale') }}</h2>
    </x-slot>

    @php($rows = old('items', [['service_id' => null, 'quantity' => 1, 'sheets_used' => 1, 'amount' => null]]))

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('staff.walkin.store') }}" data-walkin-form class="bg-white shadow-sm sm:rounded-lg p-6 space-y-5">
                @csrf

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-gray-600">
                            <tr>
                                <th class="py-2 pe-2 font-medium">{{ __('Service') }}</th>
                                <th class="py-2 px-2 font-medium">{{ __('Quantity') }}</th>
                                <th class="py-2 px-2 font-medium">{{ __('Sheets used') }}</th>
                                <th class="py-2 px-2 font-medium">{{ __('Amount (₱)') }}</th>
                                <th class="py-2 ps-2"></th>
                            </tr>
                        </thead>
                        <tbody data-walkin-rows>
                            @foreach ($rows as $i => $row)
                                <tr data-walkin-row>
                                    <td class="py-1 pe-2">
                                        <select name="items[{{ $i }}][service_id]" required data-field="service_id" class="w-full rounded-md border-gray-300 text-sm">
                                            <option value="">{{ __('Choose…') }}</option>
                                            @foreach ($services as $service)
                                                <option value="{{ $service->id }}" data-price="{{ $service->price }}" @selected((int) ($row['service_id'] ?? 0) === $service->id)>
                                                    {{ $service->name }} (₱{{ $service->price }}/{{ $service->unit }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('items.'.$i.'.service_id')" class="mt-1" />
                                    </td>
                                    <td class="py-1 px-2">
                                        <input type="number" name="items[{{ $i }}][quantity]" min="1" required data-field="quantity" value="{{ $row['quantity'] ?? 1 }}" class="w-24 rounded-md border-gray-300 text-sm">
                                        <x-input-error :messages="$errors->get('items.'.$i.'.quantity')" class="mt-1" />
                                    </td>
                                    <td class="py-1 px-2">
                                        <input type="number" name="items[{{ $i }}][sheets_used]" min="0" required data-field="sheets_used" value="{{ $row['sheets_used'] ?? 1 }}" class="w-24 rounded-md border-gray-300 text-sm">
                                        <x-input-error :messages="$errors->get('items.'.$i.'.sheets_used')" class="mt-1" />
                                    </td>
                                    <td class="py-1 px-2">
                                        <input type="number" name="items[{{ $i }}][amount]" min="0" step="0.01" required data-field="amount" value="{{ $row['amount'] ?? '' }}" class="w-28 rounded-md border-gray-300 text-sm">
                                        <x-input-error :messages="$errors->get('items.'.$i.'.amount')" class="mt-1" />
                                    </td>
                                    <td class="py-1 ps-2">
                                        <button type="button" data-walkin-remove class="text-red-600 text-sm hover:underline">{{ __('Remove') }}</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-input-error :messages="$errors->get('items')" />

                <div class="flex items-center justify-between">
                    <button type="button" data-walkin-add class="text-sm text-indigo-600 hover:underline">{{ __('+ Add item') }}</button>
                    <p class="text-lg font-semibold">{{ __('Total') }}: ₱<span data-walkin-total>0.00</span></p>
                </div>

                <fieldset class="flex flex-wrap items-end gap-4">
                    <legend class="sr-only">{{ __('Payment') }}</legend>
                    <label class="flex items-center gap-2"><input type="radio" name="payment_method" value="cash" required @checked(old('payment_method', 'cash') === 'cash')> {{ __('Cash') }}</label>
                    <label class="flex items-center gap-2"><input type="radio" name="payment_method" value="gcash" @checked(old('payment_method') === 'gcash')> {{ __('GCash') }}</label>
                    <div>
                        <x-input-label for="reference_no" :value="__('GCash reference number')" />
                        <x-text-input id="reference_no" name="reference_no" class="mt-1 block w-56 font-mono" :value="old('reference_no')" autocomplete="off" />
                    </div>
                </fieldset>
                <x-input-error :messages="$errors->get('payment_method')" />
                <x-input-error :messages="$errors->get('reference_no')" />

                <div class="flex justify-end">
                    <x-primary-button>{{ __('Record sale') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
