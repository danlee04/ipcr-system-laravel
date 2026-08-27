{{-- Guarded because this can also be fetched on its own, and by then
     the period it was about may have been deleted. --}}
@if ($period === null)
    <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-500/20">
        No rating period to report on.
    </div>
@else
{{-- The hospital's own line. Every heading below reads the same
     way, so one habit covers the whole sheet. --}}
<div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
    <h3 class="text-base font-semibold text-gray-900">{{ $period->name }}</h3>
    <p class="mt-0.5 text-sm text-gray-600">
        {{ $period->start_date?->format('d M Y') }} – {{ $period->end_date?->format('d M Y') }}
    </p>
    <x-admin.summary-tally :tally="$overall" size="lg" class="mt-3" />
</div>

@forelse ($gathered as $divisionName => $sections)
    @php $inDivision = $sections->flatten(1); @endphp

    <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-950/5">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-6 py-4">
            <h3 class="text-sm font-semibold text-gray-900">{{ $divisionName }}</h3>
            <x-admin.summary-tally :tally="\App\Support\SummaryTally::of($inDivision)" />
        </div>

        @foreach ($sections as $sectionName => $rows)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-3">
                <h4 class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $sectionName }}
                </h4>
                <x-admin.summary-tally :tally="\App\Support\SummaryTally::of($rows)" />
            </div>

            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-white">
                    <tr>
                        <th
                            class="px-6 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-400">
                            Employee</th>
                        <th
                            class="px-6 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-400">
                            Position</th>
                        <th
                            class="px-6 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-400">
                            Status</th>
                        <th
                            class="px-6 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-400">
                            Rating</th>
                        <th
                            class="px-6 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-400">
                            Adjectival</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rows as $row)
                        <tr>
                            <td class="px-6 py-3 text-sm">
                                <span
                                    class="block font-medium text-gray-900">{{ $row->employee->full_name }}</span>
                                @if ($row->employee->employee_number)
                                    <span
                                        class="block font-data text-xs text-gray-500">{{ $row->employee->employee_number }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600">
                                {{ $row->employee->position?->title ?? '—' }}</td>
                            <td class="px-6 py-3 text-sm">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @if ($row->status())
                                        <span
                                            class="{{ $chip }} {{ $row->status()->badgeClasses() }}">{{ $row->statusLabel() }}</span>
                                    @else
                                        <span
                                            class="{{ $chip }} bg-gray-100 text-gray-600 ring-gray-500/20">Not started</span>
                                    @endif

                                    <x-ipcr.late-badge :ipcr="$row->ipcr" />
                                </div>
                            </td>
                            <td class="px-6 py-3 text-right font-data text-sm text-gray-900">
                                {{ $row->approvedRating() === null ? '—' : number_format($row->approvedRating(), 2) }}
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600">
                                {{ $row->isApproved() ? ($row->ipcr->final_adjectival_rating ?? '—') : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    </div>
@empty
    <div class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-950/5">
        No active employees match these filters.
    </div>
@endforelse
@endif
