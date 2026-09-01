@props(['records', 'head', 'period', 'unit'])

@php
    use App\Enums\IpcrStatus;

    /*
     * Whose turn it is.
     *
     * A head is named on a sheet twice over the life of it - as the assessor
     * and, for the people below their section heads, as the final approver -
     * but only ever at one status at a time. Anything else is somebody else's
     * turn, and offering a Review button for it would send them to a page with
     * no form on it.
     */
    $isTheirs = fn($ipcr): bool => ($ipcr->status === IpcrStatus::Submitted && $ipcr->assessor_employee_id === $head->id)
        || ($ipcr->status === IpcrStatus::Assessed && $ipcr->final_approver_employee_id === $head->id);

    $rows = $records->sortBy(fn(array $row): string => $row['employee']->last_name)->values();

    $waiting = $rows->filter(fn(array $row): bool => $isTheirs($row['ipcr']))->count();
@endphp

<div data-head-records class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-5 py-4">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-gray-900">IPCR records</h3>
            <p class="mt-0.5 text-xs text-gray-500">
                Sheets sent in by {{ $unit }}@if ($period), for {{ $period->name }}@endif.
            </p>
        </div>

        @if ($waiting > 0)
            <span
                class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-500/20">
                {{ $waiting }} waiting on you
            </span>
        @endif
    </div>

    @if ($rows->isEmpty())
        <p class="px-5 py-10 text-center text-sm text-gray-400">
            Nothing has been sent in yet.
        </p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left font-data text-[0.6875rem] uppercase tracking-wider text-gray-500">
                        <th class="px-5 py-2.5 font-medium">Employee</th>
                        <th class="px-3 py-2.5 font-medium">Sent in</th>
                        <th class="px-3 py-2.5 font-medium">Status</th>
                        <th class="px-3 py-2.5 text-center font-medium">Rating</th>
                        <th class="px-5 py-2.5 text-end font-medium">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $person = $row['employee'];
                            $ipcr = $row['ipcr'];
                            $mine = $isTheirs($ipcr);
                        @endphp

                        <tr class="border-b border-gray-50 last:border-0">
                            <td class="px-5 py-3">
                                <span class="block truncate font-medium text-gray-900">{{ $person->full_name }}</span>
                                <span class="block truncate text-xs text-gray-500">
                                    {{ $person->position?->title ?? $person->section?->name ?? '—' }}
                                </span>
                            </td>

                            <td class="whitespace-nowrap px-3 py-3 font-data text-xs text-gray-500">
                                {{ $ipcr->submitted_at?->format('d M Y') ?? '—' }}
                            </td>

                            <td class="px-3 py-3">
                                <x-status-badge :status="$ipcr->status" />
                            </td>

                            <td class="whitespace-nowrap px-3 py-3 text-center font-data">
                                @if ($ipcr->final_numerical_rating !== null)
                                    <span
                                        class="text-base font-semibold text-gray-900">{{ number_format((float) $ipcr->final_numerical_rating, 2) }}</span>
                                    <span class="text-xs text-gray-400">/5</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>

                            <td class="px-5 py-3 text-end">
                                @if ($mine)
                                    <a href="{{ route('ipcrs.show', $ipcr) }}"
                                        class="inline-flex items-center rounded-md bg-nav-900 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-nav-800">
                                        Review
                                    </a>
                                @else
                                    <a href="{{ route('ipcrs.show', $ipcr) }}"
                                        class="inline-flex items-center rounded-md px-3 py-1.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-300 transition-colors hover:bg-gray-50 hover:text-gray-900">
                                        View
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
