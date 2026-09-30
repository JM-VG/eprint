<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Price List') }}</h2>
            <a href="{{ route('admin.services.create') }}" class="px-3 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700">{{ __('Add service') }}</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <p class="text-sm text-gray-600">{{ __('One price list for both locations. Documents are priced per page, photos per set.') }}</p>

            @foreach (['document' => __('Documents'), 'photo' => __('Photos')] as $type => $heading)
                <section class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                    <h3 class="px-4 pt-4 font-medium text-gray-900">{{ $heading }}</h3>
                    <table class="mt-2 min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ __('Name') }}</th>
                                <th class="px-4 py-2 font-medium">{{ $type === 'photo' ? __('Pieces per set') : __('Paper') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('Color') }}</th>
                                <th class="px-4 py-2 font-medium text-right">{{ __('Price') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('Status') }}</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($services[$type] ?? [] as $service)
                                <tr class="{{ $service->is_active ? '' : 'text-gray-400' }}">
                                    <td class="px-4 py-2">{{ $service->name }}</td>
                                    <td class="px-4 py-2">{{ $type === 'photo' ? $service->pieces_per_set : $service->paper_size }}</td>
                                    <td class="px-4 py-2">{{ $service->color_mode === 'bw' ? __('Black & White') : __('Color') }}</td>
                                    <td class="px-4 py-2 text-right font-mono">₱{{ $service->price }} / {{ $service->unit }}</td>
                                    <td class="px-4 py-2">{{ $service->is_active ? __('Active') : __('Inactive') }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <a href="{{ route('admin.services.edit', $service) }}" class="text-indigo-600 hover:underline">{{ __('Edit') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-4 text-center text-gray-500">{{ __('No services yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>
