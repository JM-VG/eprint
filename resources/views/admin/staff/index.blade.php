<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Staff') }}</h2>
            <a href="{{ route('admin.staff.create') }}" class="px-3 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700">{{ __('Add staff') }}</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('Username') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Role') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($staff as $member)
                            <tr>
                                <td class="px-4 py-3 font-mono">{{ $member->username }}</td>
                                <td class="px-4 py-3">{{ $member->last_name }}, {{ $member->first_name }}</td>
                                <td class="px-4 py-3">{{ ucfirst($member->role->value) }}</td>
                                <td class="px-4 py-3">
                                    {{ ucfirst($member->status->value) }}
                                    @if ($member->must_change_password)
                                        <span class="ms-1 text-xs text-yellow-700">({{ __('temporary password') }})</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('admin.staff.edit', $member) }}" class="px-3 py-1.5 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">{{ __('Edit') }}</a>

                                        @unless ($member->is(auth()->user()))
                                            <form method="POST" action="{{ route('admin.users.reset-password', $member) }}"
                                                  onsubmit="return confirm('Reset this password to a temporary one?')">
                                                @csrf
                                                <button class="px-3 py-1.5 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">{{ __('Reset password') }}</button>
                                            </form>

                                            @if ($member->status === \App\Enums\UserStatus::Disabled)
                                                <form method="POST" action="{{ route('admin.users.enable', $member) }}">
                                                    @csrf
                                                    <button class="px-3 py-1.5 rounded-md bg-green-600 text-white hover:bg-green-700">{{ __('Enable') }}</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('admin.users.disable', $member) }}"
                                                      onsubmit="return confirm('Disable this account?')">
                                                    @csrf
                                                    <button class="px-3 py-1.5 rounded-md bg-red-600 text-white hover:bg-red-700">{{ __('Disable') }}</button>
                                                </form>
                                            @endif
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ __('No staff accounts yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $staff->links() }}
        </div>
    </div>
</x-app-layout>
