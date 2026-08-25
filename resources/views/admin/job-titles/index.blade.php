<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Job Titles') }}</h2>
    </x-slot>

    <x-page-container class="space-y-6">
        <p class="text-sm text-gray-600">
            {{ $positions->count() }} position(s), {{ $designations->count() }} designation(s). Tab: {{ $tab }}
        </p>
    </x-page-container>
</x-app-layout>
