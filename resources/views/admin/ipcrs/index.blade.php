<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('All IPCRs') }}</h2>
    </x-slot>

    <x-page-container class="space-y-6">
        <x-admin.flash />

        <p class="max-w-3xl text-sm text-gray-600">
            Every IPCR in the hospital. Routing is automatic — the Section Head assesses, the Division Head gives the
            final approval — so <strong>Approvers</strong> appears only where the org chart cannot work that out, such
            as the Chief of Hospital's own IPCR. <strong>Reopen</strong> undoes an approval. Both are recorded in the
            IPCR's history.
        </p>

        <x-admin.filter-bar :action="route('admin.ipcrs.index')" placeholder="Search by employee name or number">
            <label class="block">
                <span class="sr-only">Status</span>
                <select name="status" class="w-40 rounded-lg border-gray-300 text-sm">
                    <option value="">Any status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                            {{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="sr-only">Period</span>
                <select name="period" class="w-40 rounded-lg border-gray-300 text-sm">
                    <option value="">All periods</option>
                    @foreach ($periods as $period)
                        <option value="{{ $period->id }}" @selected(request('period') == $period->id)>{{ $period->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="sr-only">Division</span>
                <select name="division" class="w-40 rounded-lg border-gray-300 text-sm">
                    <option value="">All divisions</option>
                    @foreach ($divisions as $division)
                        <option value="{{ $division->id }}" @selected(request('division') == $division->id)>
                            {{ $division->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="sr-only">Section</span>
                <select name="section" class="w-40 rounded-lg border-gray-300 text-sm">
                    <option value="">All sections</option>
                    @foreach ($sections as $section)
                        <option value="{{ $section->id }}" @selected(request('section') == $section->id)>
                            {{ $section->name }}</option>
                    @endforeach
                </select>
            </label>
        </x-admin.filter-bar>

        <x-admin.table>
            <x-slot:head>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Employee</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Period</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">With</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Rating</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Updated</th>
                <th class="px-6 py-3"></th>
            </x-slot:head>

            @forelse ($ipcrs as $ipcr)
                <tr>
                    <td class="px-6 py-4 text-sm">
                        <span class="block font-medium text-gray-900">{{ $ipcr->employee?->full_name ?? '—' }}</span>
                        <span class="block text-xs text-gray-500">
                            {{ $ipcr->employee?->section?->name ?? ($ipcr->employee?->division?->name ?? '—') }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700">{{ $ipcr->period?->name ?? '—' }}</td>
                    <td class="px-6 py-4">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <x-status-badge :status="$ipcr->status" />
                            <x-ipcr.late-badge :ipcr="$ipcr" />
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        {{-- Whoever the IPCR is sitting with right now, and the
                             post that put them there. --}}
                        @if ($ipcr->isAwaitingAssessment())
                            {{ $ipcr->assessor?->nameWithPost() ?? 'Not routed' }}
                        @elseif ($ipcr->isAwaitingFinalRating())
                            {{ $ipcr->finalApprover?->nameWithPost() ?? 'Not routed' }}
                        @else
                            <span class="text-gray-400">—</span>
                        @endif

                        @if ($ipcr->hasOverriddenChain())
                            {{-- Worth saying on the list: a chain that did not
                                 come from the org chart is the first thing to
                                 check when the routing looks wrong. --}}
                            <span class="mt-1 block text-xs text-amber-700">chain set by hand</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm">
                        @if ($ipcr->final_numerical_rating !== null)
                            <span
                                class="font-data font-medium text-gray-900">{{ number_format((float) $ipcr->final_numerical_rating, 2) }}</span>
                            <span class="block text-xs text-gray-500">{{ $ipcr->final_adjectival_rating }}</span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 font-data text-xs text-gray-500">{{ $ipcr->updated_at?->format('d M Y') }}
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-end gap-3">
                            @can('reroute', $ipcr)
                                <button type="button" x-data
                                    x-on:click="$dispatch('open-modal', 'chain-{{ $ipcr->id }}')"
                                    class="text-sm font-medium text-gray-900 hover:underline">Approvers</button>
                            @endcan

                            @can('reopen', $ipcr)
                                <button type="button" x-data
                                    x-on:click="$dispatch('open-modal', 'reopen-{{ $ipcr->id }}')"
                                    class="text-sm font-medium text-red-600 hover:underline">Reopen</button>
                            @endcan

                            <a href="{{ route('ipcrs.show', $ipcr) }}"
                                class="inline-flex items-center rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 transition-colors hover:bg-gray-50">
                                Open
                            </a>
                        </div>
                    </td>
                </tr>

                <x-admin.ipcr-chain-modals :ipcr="$ipcr" :employees="$employees" />
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">
                        @if (request()->hasAny(['search', 'status', 'period', 'division', 'section']))
                            No IPCRs match this search.
                        @else
                            No IPCRs have been created yet.
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-admin.table>

        {{ $ipcrs->links() }}
    </x-page-container>
</x-app-layout>
