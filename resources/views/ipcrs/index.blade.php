<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My IPCRs') }}
        </h2>
    </x-slot>

    <x-page-container class="space-y-6">

            @if (session('status'))
                <div class="rounded-md bg-emerald-50 p-4 text-sm text-emerald-800 ring-1 ring-emerald-500/20">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-500/20">
                    {{ session('error') }}
                </div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-600">{{ $ipcrs->total() }} total record(s)</p>

                @if ($canCreate)
                    <button type="button" x-data
                        x-on:click="$dispatch('open-modal', 'new-ipcr')"
                        class="inline-flex items-center rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2">
                        + New IPCR
                    </button>
                @elseif ($existingForPeriod)
                    <p class="text-sm text-gray-600">
                        You already have an IPCR for {{ $period->name }}.
                        <a href="{{ route('ipcrs.show', $existingForPeriod) }}"
                            class="font-medium text-nav-900 underline hover:no-underline">Open it</a>
                    </p>
                @else
                    <p class="text-sm text-gray-600">No open rating period right now. Contact HR/Admin.</p>
                @endif
            </div>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg ring-1 ring-gray-950/5">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Period</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Final Rating</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($ipcrs as $ipcr)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ $ipcr->period->name }}</td>
                                <td class="px-6 py-4">
                                    <x-status-badge :status="$ipcr->status" />
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    {{ $ipcr->final_adjectival_rating ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('ipcrs.show', $ipcr) }}"
                                            class="font-medium text-gray-900 hover:underline">View</a>

                                        @can('delete', $ipcr)
                                            <form method="POST" action="{{ route('ipcrs.destroy', $ipcr) }}"
                                                onsubmit="return confirm('Delete your draft IPCR for {{ $ipcr->period->name }}? This cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="font-medium text-red-600 hover:underline">Delete</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">
                                    No IPCR records yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $ipcrs->links() }}

            @if ($canCreate)
                {{-- The employee chooses here, before reaching any form. --}}
                <x-modal name="new-ipcr" focusable max-width="xl">
                    <form method="POST" action="{{ route('ipcrs.store') }}" class="p-6 space-y-5">
                        @csrf

                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Start a new IPCR</h2>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ $period->name }} &middot;
                                {{ $period->start_date->format('M d, Y') }} –
                                {{ $period->end_date->format('M d, Y') }}
                            </p>
                        </div>

                        <div class="grid grid-cols-4 gap-2">
                            @foreach (['Core' => $catalog->core, 'Strategic' => $catalog->strategic, 'Support' => $catalog->support] as $label => $functions)
                                <div class="rounded-md bg-gray-50 p-2 text-center">
                                    <p class="text-lg font-semibold text-gray-900">{{ $functions->count() }}</p>
                                    <p class="text-xs text-gray-500">{{ $label }}</p>
                                </div>
                            @endforeach
                        </div>

                        <p class="text-xs text-gray-500">
                            Catalog functions available to you. You pick the specific ones on the next screen —
                            nothing is added automatically.
                        </p>

                        <x-ipcr-mode-choice />

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" x-on:click="$dispatch('close-modal', 'new-ipcr')"
                                class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 transition-colors hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2">
                                Create Draft IPCR
                            </button>
                        </div>
                    </form>
                </x-modal>
            @endif
</x-page-container>
</x-app-layout>
