<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Rating Periods') }}</h2>
            <button type="button" x-data x-on:click="$dispatch('open-modal', 'create-period')"
                class="inline-flex items-center rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-nav-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-seal focus-visible:ring-offset-2">
                + New Period
            </button>
        </div>
    </x-slot>

    <x-page-container class="space-y-6">
        <x-admin.flash />

        <p class="max-w-3xl text-sm text-gray-600">
            Employees can only start an IPCR while a period is open. Closing a period is how you stop new ones
            being created once the cycle is over — it never touches the IPCRs already inside it.
        </p>

        {{-- One period is active and every IPCR is created against it. Making
             one active closes whichever was, so the ambiguity this box used to
             warn about can no longer be created. --}}
        @if ($activePeriod === null)
            <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-500/20">
                <strong>No rating period is active.</strong> Nobody can start a new IPCR until you make one active.
            </div>
        @else
            <div class="rounded-md bg-emerald-50 p-4 text-sm text-emerald-900 ring-1 ring-emerald-500/20">
                New IPCRs are created against <strong>{{ $activePeriod->name }}</strong>. Making another period
                active closes this one.
            </div>
        @endif

        <x-admin.table>
            <x-slot:head>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Period</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Covers</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Deadline
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">IPCRs</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                <th class="px-6 py-3"></th>
            </x-slot:head>

            @forelse ($periods as $period)
                @php $type = \App\Enums\IpcrPeriodType::tryFrom($period->type); @endphp
                <tr>
                    <td class="px-6 py-4 text-sm">
                        <span class="font-medium text-gray-900">{{ $period->name }}</span>
                        @if ($activePeriod && $period->id === $activePeriod->id)
                            <span
                                class="ms-2 inline-flex items-center rounded-full bg-seal/15 px-2 py-0.5 font-data text-[0.625rem] uppercase tracking-wide text-amber-800 ring-1 ring-inset ring-amber-500/30">Active</span>
                        @endif
                        <span class="block font-data text-xs text-gray-500">{{ $period->year }}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700">{{ $type?->label() ?? $period->type }}</td>
                    <td class="px-6 py-4 font-data text-sm text-gray-600">
                        {{ $period->start_date?->format('d M Y') }} – {{ $period->end_date?->format('d M Y') }}
                    </td>
                    <td class="px-6 py-4 font-data text-sm text-gray-600">
                        {{ $period->submission_deadline?->format('d M Y') ?? '—' }}
                    </td>
                    <td class="px-6 py-4 font-data text-sm text-gray-600">{{ $period->ipcrs_count }}</td>
                    <td class="px-6 py-4">
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $period->status === 'open' ? 'bg-emerald-100 text-emerald-800 ring-emerald-500/20' : 'bg-gray-100 text-gray-600 ring-gray-500/20' }}">
                            {{ $period->status === 'open' ? 'Active' : 'Closed' }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-end gap-3">
                            <button type="button" x-data
                                x-on:click="$dispatch('open-modal', 'edit-period-{{ $period->id }}')"
                                class="text-sm font-medium text-gray-900 hover:underline">Edit</button>

                            {{-- Making one active closes whichever was, so the
                                 button says so before it is pressed. --}}
                            <form method="POST" action="{{ route('admin.periods.status', $period) }}"
                                @if ($period->status !== 'open' && $activePeriod)
                                    onsubmit="return confirm('Make this the active period? {{ addslashes($activePeriod->name) }} will be closed.');"
                                @endif>
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="open" value="{{ $period->status === 'open' ? 0 : 1 }}">
                                <button type="submit" class="text-sm font-medium text-gray-700 hover:underline">
                                    {{ $period->status === 'open' ? 'Close' : 'Make active' }}
                                </button>
                            </form>

                            @if ($reports[$period->id]->deletable)
                                <form method="POST" action="{{ route('admin.periods.destroy', $period) }}"
                                    onsubmit="return confirm('Delete this period permanently? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="text-sm font-medium text-red-600 hover:underline">Delete</button>
                                </form>
                            @else
                                <span class="cursor-not-allowed text-sm font-medium text-gray-300"
                                    title="{{ $reports[$period->id]->message() }}">Delete</span>
                            @endif
                        </div>
                    </td>
                </tr>

                <x-modal name="edit-period-{{ $period->id }}" focusable max-width="2xl">
                    <form method="POST" action="{{ route('admin.periods.update', $period) }}" class="space-y-4 p-6">
                        @csrf
                        @method('PUT')
                        <h2 class="text-lg font-semibold text-gray-900">Edit rating period</h2>
                        <x-admin.period-fields :period="$period" />

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button"
                                x-on:click="$dispatch('close-modal', 'edit-period-{{ $period->id }}')"
                                class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                            <button type="submit"
                                class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">Save
                                changes</button>
                        </div>
                    </form>
                </x-modal>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">
                        No rating periods yet. Create one before anyone can start an IPCR.
                    </td>
                </tr>
            @endforelse
        </x-admin.table>

        <x-modal name="create-period" focusable max-width="2xl">
            <form method="POST" action="{{ route('admin.periods.store') }}" class="space-y-4 p-6">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">New rating period</h2>
                <p class="text-sm text-gray-600">It opens immediately — employees can start an IPCR against it
                    straight away.</p>

                <x-admin.period-fields />

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" x-on:click="$dispatch('close-modal', 'create-period')"
                        class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                    <button type="submit"
                        class="rounded-md bg-nav-900 px-4 py-2 text-sm font-semibold text-white hover:bg-nav-800">Open
                        period</button>
                </div>
            </form>
        </x-modal>
    </x-page-container>
</x-app-layout>
