<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <x-page-container>
        {{-- The two edit forms sit side by side once there is room for them;
             deleting the account stays on its own row, away from the rest. --}}
        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-8">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-8">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="mt-6 rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-8">
            <div class="max-w-2xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </x-page-container>
</x-app-layout>
