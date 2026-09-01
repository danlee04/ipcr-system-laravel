@props(['team', 'period'])

@php
    use App\Enums\IpcrStatus;

    /*
     * Whoever needs chasing first.
     *
     * Nothing started, then a draft that has never left their hands, then the
     * ones already moving. Inside each group, alphabetical - the head is
     * looking for a name as often as for a state.
     */
    $rank = fn($ipcr): int => match (true) {
        $ipcr === null => 0,
        $ipcr->status === IpcrStatus::Draft => 1,
        $ipcr->status === IpcrStatus::Returned => 2,
        default => 3,
    };

    $sorted = $team->sortBy(fn(array $row): int => $rank($row['ipcr']))->values();

    $waiting = $team->filter(fn(array $row): bool => $rank($row['ipcr']) < 2)->count();
@endphp

<div data-team-roster class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
    <div class="border-b border-gray-100 px-5 py-4">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h3 class="text-sm font-semibold text-gray-900">My people</h3>
            <span class="font-data text-xs text-gray-500">{{ $team->count() }}</span>
        </div>

        <p class="mt-0.5 text-xs text-gray-500">
            @if (! $period)
                No rating period is open, so nobody can start one yet.
            @elseif ($waiting === 0)
                Everyone has sent theirs in for {{ $period->name }}.
            @else
                <strong class="text-amber-800">{{ $waiting }}</strong>
                of {{ $team->count() }} {{ $waiting === 1 ? 'has' : 'have' }} not sent anything in for
                {{ $period->name }}.
            @endif
        </p>
    </div>

    @forelse ($sorted as $row)
        @php
            $person = $row['employee'];
            $ipcr = $row['ipcr'];
        @endphp

        <div class="flex items-center gap-3 border-b border-gray-50 px-5 py-2.5 last:border-0">
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-medium text-gray-900">{{ $person->full_name }}</span>
                <span class="block truncate text-xs text-gray-500">
                    {{ $person->position?->title ?? $person->section?->name ?? '—' }}
                </span>
            </span>

            @if ($ipcr)
                <x-status-badge :status="$ipcr->status" />
            @else
                {{-- Not a status: there is no sheet at all. It reads as the
                     absence it is rather than borrowing Draft's colours. --}}
                <span
                    class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-500/20">
                    Not started
                </span>
            @endif
        </div>
    @empty
        <p class="px-5 py-10 text-center text-sm text-gray-400">
            Nobody is filed under you yet. HR sets that on the employee record.
        </p>
    @endforelse
</div>
