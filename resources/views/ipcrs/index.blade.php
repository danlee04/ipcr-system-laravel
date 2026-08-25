<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My IPCRs') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

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

            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-600">{{ $ipcrs->total() }} total record(s)</p>
                <a href="{{ route('ipcrs.create') }}"
                    class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                    + New IPCR
                </a>
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
                                    <a href="{{ route('ipcrs.show', $ipcr) }}"
                                        class="font-medium text-gray-900 hover:underline">View</a>
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
        </div>
    </div>
</x-app-layout>
