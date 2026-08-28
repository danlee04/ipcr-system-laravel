<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <x-page-container class="space-y-4">
        {{-- Read first, edit second. What HR holds and where the IPCR goes are
             what people come here to check; the two things they can actually
             change are short forms, and they sit underneath. --}}
        @include('profile.partials.employee-record', [
            'employee' => $employee,
            'chain' => $chain,
            'chainProblem' => $chainProblem,
        ])

        {{-- The two short forms, side by side once there is room. Neither is
             long enough to earn a full-width row of its own. --}}
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6">
                @include('profile.partials.update-password-form')
            </div>
        </div>
    </x-page-container>
</x-app-layout>
