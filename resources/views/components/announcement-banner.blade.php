@php($announcements = \App\Models\Announcement::active()->latest()->limit(3)->get())

@if ($announcements->isNotEmpty())
    <div data-announcement-banner class="w-full bg-amber-50 border-b border-amber-200 text-amber-900 text-sm" role="region" aria-label="{{ __('Announcements') }}">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 space-y-1">
            @foreach ($announcements as $announcement)
                <p><strong>{{ $announcement->title }}:</strong> {{ $announcement->body }}</p>
            @endforeach
        </div>
    </div>
@endif
