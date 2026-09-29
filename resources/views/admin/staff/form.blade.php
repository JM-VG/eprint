<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $user->exists ? __('Edit :username', ['username' => $user->username]) : __('Add staff') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST"
                  action="{{ $user->exists ? route('admin.staff.update', $user) : route('admin.staff.store') }}"
                  class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                @csrf
                @if ($user->exists)
                    @method('PUT')
                @endif

                <div>
                    <x-input-label for="username" :value="__('Username')" />
                    <x-text-input id="username" name="username" class="block mt-1 w-full" :value="old('username', $user->username)" required autocomplete="off" />
                    <p class="mt-1 text-xs text-gray-500">{{ __('Letters, numbers, dots, dashes and underscores. Staff log in with this.') }}</p>
                    <x-input-error :messages="$errors->get('username')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="first_name" :value="__('First Name')" />
                        <x-text-input id="first_name" name="first_name" class="block mt-1 w-full" :value="old('first_name', $user->first_name)" required />
                        <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="last_name" :value="__('Last Name')" />
                        <x-text-input id="last_name" name="last_name" class="block mt-1 w-full" :value="old('last_name', $user->last_name)" required />
                        <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <x-input-label for="role" :value="__('Role')" />
                    <select id="role" name="role" class="block mt-1 w-full rounded-md border-gray-300">
                        @foreach ([\App\Enums\Role::Staff, \App\Enums\Role::Admin] as $role)
                            <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ ucfirst($role->value) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('role')" class="mt-2" />
                </div>

                @unless ($user->exists)
                    <p class="text-sm text-gray-600">{{ __('A temporary password is generated and shown once after saving. The staff member must change it on first login.') }}</p>
                @endunless

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.staff.index') }}" class="px-4 py-2 text-sm text-gray-700">{{ __('Cancel') }}</a>
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
