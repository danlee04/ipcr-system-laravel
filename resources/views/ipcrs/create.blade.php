<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Start New IPCR') }}
        </h2>
    </x-slot>

    <x-page-container class="max-w-3xl space-y-6">

            <div class="bg-white shadow-sm sm:rounded-lg ring-1 ring-gray-950/5 p-6 space-y-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">{{ $period->name }}</h3>
                    <p class="text-sm text-gray-600">
                        {{ $period->start_date->format('M d, Y') }} – {{ $period->end_date->format('M d, Y') }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="rounded-md bg-gray-50 p-3 text-center">
                        <p class="text-2xl font-semibold text-gray-900">{{ $catalog->core->count() }}</p>
                        <p class="text-xs text-gray-500">Core</p>
                    </div>
                    <div class="rounded-md bg-gray-50 p-3 text-center">
                        <p class="text-2xl font-semibold text-gray-900">{{ $catalog->strategic->count() }}</p>
                        <p class="text-xs text-gray-500">Strategic</p>
                    </div>
                    <div class="rounded-md bg-gray-50 p-3 text-center">
                        <p class="text-2xl font-semibold text-gray-900">{{ $catalog->support->count() }}</p>
                        <p class="text-xs text-gray-500">Support</p>
                    </div>
                    <div class="rounded-md bg-gray-50 p-3 text-center">
                        <p class="text-2xl font-semibold text-gray-900">{{ $catalog->common->count() }}</p>
                        <p class="text-xs text-gray-500">Common</p>
                    </div>
                </div>

                <p class="text-sm text-gray-600">
                    These counts show how many catalog functions are available for you to choose from.
                    You will pick and add the specific ones on the next screen — nothing is added automatically.
                </p>

                <form method="POST" action="{{ route('ipcrs.store') }}" class="space-y-4">
                    @csrf

                    <p class="text-sm text-gray-600">
                        This creates a <span class="font-medium text-gray-900">Targets only</span> IPCR, and that
                        cannot be changed afterwards. To record actual accomplishments as well, start it from
                        <a href="{{ route('ipcrs.index') }}" class="font-medium text-nav-900 underline hover:no-underline">My IPCRs</a>
                        instead.
                    </p>

                    <button type="submit"
                        class="inline-flex items-center rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal focus-visible:ring-offset-2">
                        Create Draft IPCR
                    </button>
                </form>
            </div>

</x-page-container>
</x-app-layout>
