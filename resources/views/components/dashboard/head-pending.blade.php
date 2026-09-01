@props(['pending', 'head', 'period'])

@php
    use App\Enums\IpcrStatus;

    /*
     * The people to chase, worst first.
     *
     * Nothing started at all, then a draft that has never left their hands,
     * then a sheet that came back for revision. Inside each group,
     * alphabetical - a head looks for a name as often as for a state.
     */
    $rank = fn($ipcr): int => match (true) {
        $ipcr === null => 0,
        $ipcr->status === IpcrStatus::Draft => 1,
        default => 2,
    };

    $rows = $pending
        ->sortBy(fn(array $row): string => sprintf('%d%s', $rank($row['ipcr']), $row['employee']->last_name))
        ->values();

    // A division head sees several sections at once, so the name alone does
    // not say who to ask about somebody.
    $showsSection = $head->headedDivision !== null;

    $initials = fn($person): string => mb_strtoupper(mb_substr($person->first_name, 0, 1) . mb_substr($person->last_name, 0, 1));
@endphp

<div data-head-pending class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-5 py-4">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-gray-900">Still to send in</h3>
            <p class="mt-0.5 text-xs text-gray-500">
                @if (! $period)
                    No rating period is open, so nobody can start one yet.
                @else
                    Nothing has reached an approver for {{ $period->name }}.
                @endif
            </p>
        </div>

        @if ($rows->isNotEmpty())
            <span
                class="inline-flex min-w-6 items-center justify-center rounded-full bg-red-50 px-2 py-0.5 font-data text-xs font-semibold text-red-700 ring-1 ring-inset ring-red-500/20">
                {{ $rows->count() }}
            </span>
        @endif
    </div>

    @if ($rows->isEmpty())
        <div class="px-5 py-10 text-center">
            <p class="text-sm font-medium text-gray-900">All caught up</p>
            <p class="mt-1 text-xs text-gray-500">Everyone has sent their IPCR in.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left font-data text-[0.6875rem] uppercase tracking-wider text-gray-500">
                        <th class="px-5 py-2.5 font-medium">Employee</th>
                        <th class="px-3 py-2.5 font-medium">Position</th>
                        @if ($showsSection)
                            <th class="px-3 py-2.5 font-medium">Section</th>
                        @endif
                        <th class="px-5 py-2.5 text-end font-medium">Status</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $person = $row['employee'];
                            $ipcr = $row['ipcr'];
                        @endphp

                        <tr class="border-b border-gray-50 last:border-0">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span aria-hidden="true"
                                        class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gray-100 font-data text-xs font-semibold text-gray-600">
                                        {{ $initials($person) }}
                                    </span>
                                    <span class="min-w-0">
                                        <span
                                            class="block truncate font-medium text-gray-900">{{ $person->full_name }}</span>
                                        <span
                                            class="block truncate font-data text-xs text-gray-500">{{ $person->employee_number }}</span>
                                    </span>
                                </div>
                            </td>

                            <td class="px-3 py-3 text-xs text-gray-500">
                                {{ $person->position?->title ?? '—' }}
                            </td>

                            @if ($showsSection)
                                <td class="px-3 py-3 text-xs text-gray-500">
                                    {{ $person->section?->name ?? '—' }}
                                </td>
                            @endif

                            <td class="px-5 py-3 text-end">
                                @if ($ipcr)
                                    <x-status-badge :status="$ipcr->status" />
                                @else
                                    {{-- Not a status: there is no sheet at all. It reads as
                                         the absence it is rather than borrowing Draft's
                                         colours. --}}
                                    <span
                                        class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-500/20">
                                        Not started
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
