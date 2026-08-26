<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('All IPCRs') }}</h2>
    </x-slot>

    <x-page-container class="space-y-6">
        <x-admin.flash />

        <p class="max-w-3xl text-sm text-gray-600">
            Every IPCR in the hospital, read-only. Assessing and approving stay with the people in each IPCR's own
            approval chain.
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
                    <td class="px-6 py-4"><x-status-badge :status="$ipcr->status" /></td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        {{-- Whoever the IPCR is sitting with right now. --}}
                        @if ($ipcr->isAwaitingAssessment())
                            {{ $ipcr->assessor?->full_name ?? 'Not routed' }}
                        @elseif ($ipcr->isAwaitingFinalRating())
                            {{ $ipcr->finalApprover?->full_name ?? 'Not routed' }}
                        @else
                            <span class="text-gray-400">—</span>
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
                    <td class="px-6 py-4 text-end">
                        <a href="{{ route('ipcrs.show', $ipcr) }}"
                            class="inline-flex items-center rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 transition-colors hover:bg-gray-50">
                            Open
                        </a>
                    </td>
                </tr>
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
