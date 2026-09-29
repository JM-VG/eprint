<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Students') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <nav class="flex flex-wrap gap-2" aria-label="Filter by status">
                @foreach ($statuses as $option)
                    <a href="{{ route('admin.students.index', ['status' => $option->value]) }}"
                       @if ($option === $status) aria-current="page" @endif
                       class="px-3 py-1.5 rounded-full text-sm font-medium {{ $option === $status ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50' }}">
                        {{ ucfirst($option->value) }}
                    </a>
                @endforeach
            </nav>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('Student ID') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Signed up') }}</th>
                            @if ($status === \App\Enums\UserStatus::Rejected)
                                <th class="px-4 py-3 font-medium">{{ __('Reason') }}</th>
                            @endif
                            <th class="px-4 py-3 font-medium text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($students as $student)
                            <tr>
                                <td class="px-4 py-3 font-mono">{{ $student->student_id }}</td>
                                <td class="px-4 py-3">{{ $student->last_name }}, {{ $student->first_name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $student->created_at?->format('M j, Y g:i A') }}</td>
                                @if ($status === \App\Enums\UserStatus::Rejected)
                                    <td class="px-4 py-3 text-gray-600">{{ $student->rejection_reason }}</td>
                                @endif
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        @if ($student->status !== \App\Enums\UserStatus::Approved)
                                            <form method="POST" action="{{ route('admin.students.approve', $student) }}">
                                                @csrf
                                                <button class="px-3 py-1.5 rounded-md bg-green-600 text-white hover:bg-green-700">{{ __('Approve') }}</button>
                                            </form>
                                        @endif

                                        @if ($student->status === \App\Enums\UserStatus::Pending)
                                            <form method="POST" action="{{ route('admin.students.reject', $student) }}" class="flex gap-2">
                                                @csrf
                                                <label class="sr-only" for="reason-{{ $student->id }}">{{ __('Reason') }}</label>
                                                <input id="reason-{{ $student->id }}" name="reason" required maxlength="500"
                                                       placeholder="{{ __('Reason') }}"
                                                       class="w-40 rounded-md border-gray-300 text-sm">
                                                <button class="px-3 py-1.5 rounded-md bg-red-600 text-white hover:bg-red-700">{{ __('Reject') }}</button>
                                            </form>
                                        @endif

                                        @if ($student->status !== \App\Enums\UserStatus::Disabled)
                                            <form method="POST" action="{{ route('admin.users.disable', $student) }}"
                                                  onsubmit="return confirm('Disable this account?')">
                                                @csrf
                                                <button class="px-3 py-1.5 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">{{ __('Disable') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ __('No :status students.', ['status' => $status->value]) }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $students->links() }}
        </div>
    </div>
</x-app-layout>
