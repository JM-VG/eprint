@props(['status'])

@php
    $classes = match ($status) {
        \App\Enums\JobStatus::Pending => 'bg-yellow-100 text-yellow-800',
        \App\Enums\JobStatus::Accepted, \App\Enums\JobStatus::Printing => 'bg-blue-100 text-blue-800',
        \App\Enums\JobStatus::Ready => 'bg-green-100 text-green-800',
        \App\Enums\JobStatus::Claimed => 'bg-gray-100 text-gray-700',
        \App\Enums\JobStatus::Rejected, \App\Enums\JobStatus::Cancelled => 'bg-red-100 text-red-800',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-block px-2 py-0.5 rounded-full text-xs font-medium {$classes}"]) }}>
    {{ ucfirst($status->value) }}
</span>
