@php
    $styles = [
        'success' => 'bg-green-50 text-green-800 border-green-200',
        'error' => 'bg-red-50 text-red-800 border-red-200',
        'warning' => 'bg-yellow-50 text-yellow-800 border-yellow-200',
        'info' => 'bg-blue-50 text-blue-800 border-blue-200',
    ];
@endphp

@foreach ($styles as $type => $classes)
    @if (session()->has($type))
        <div data-flash="{{ $type }}"
             role="{{ $type === 'error' ? 'alert' : 'status' }}"
             class="max-w-7xl mx-auto mt-4 px-4 sm:px-6 lg:px-8">
            <div class="border rounded-md px-4 py-3 text-sm {{ $classes }}">
                {{ session($type) }}
            </div>
        </div>
    @endif
@endforeach
