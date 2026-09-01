@props(['team', 'period', 'head', 'unit'])

@php
    use App\Enums\IpcrStatus;

    /*
     * The unit a head runs, and how far it has got.
     *
     * Two tables rather than one list, because a head does two different
     * things with the same roster: they act on the sheets that have been sent
     * in, and they chase the people who have sent nothing. A single list mixed
     * both together and answered neither well.
     *
     * The line between them is the one the flow already draws: a sheet counts
     * as sent in once it is with an approver. A draft has never left the
     * employee's hands and a returned sheet is back in them, so both belong
     * with the chasing.
     */
    $sentIn = [IpcrStatus::Submitted, IpcrStatus::Assessed, IpcrStatus::Approved];
    $hasBeenSentIn = fn(array $row): bool => $row['ipcr'] !== null && in_array($row['ipcr']->status, $sentIn, true);

    $records = $team->filter($hasBeenSentIn);
    $pending = $team->reject($hasBeenSentIn);

    $total = $team->count();
    $at = fn(IpcrStatus $status): int => $team->filter(
        fn(array $row): bool => $row['ipcr']?->status === $status,
    )->count();

    // Out of the whole unit, not out of the sheets that exist - the point of
    // the strip is how much of the unit is still outstanding.
    $share = fn(int $count): int => $total > 0 ? (int) round(($count / $total) * 100) : 0;

    $assessment = $at(IpcrStatus::Submitted);
    $final = $at(IpcrStatus::Assessed);
    $approved = $at(IpcrStatus::Approved);

    // Named by the controller, so the masthead above and the strip below
    // cannot end up describing two different units.
    $unitName = $unit['name'] ?? 'your unit';
    $unitKind = mb_strtolower($unit['kind'] ?? 'unit');
@endphp

<section data-head-overview class="space-y-4">
    <div data-head-kpis class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-dashboard.kpi-card data-kpi="people" label="People in {{ $unitKind }}" :value="$total" :sub="$unitName"
            accent="teal" :percent="100">
            <x-slot:icon>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round"
                        d="M17 20h5v-2a3 3 0 0 0-5.4-1.9M17 20H7m10 0v-2c0-.7-.1-1.3-.4-1.9M7 20H2v-2a3 3 0 0 1 5.4-1.9M7 20v-2c0-.7.1-1.3.4-1.9m0 0a5 5 0 0 1 9.2 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                </svg>
            </x-slot:icon>
        </x-dashboard.kpi-card>

        <x-dashboard.kpi-card data-kpi="assessment" label="For Assessment" :value="$assessment" :denominator="$total"
            sub="sitting with an assessor" accent="amber" :percent="$share($assessment)">
            <x-slot:icon>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.6L19 9.4V19a2 2 0 0 1-2 2z" />
                </svg>
            </x-slot:icon>
        </x-dashboard.kpi-card>

        <x-dashboard.kpi-card data-kpi="final" label="For Final Approval" :value="$final" :denominator="$total"
            sub="assessed, awaiting the final word" accent="blue" :percent="$share($final)">
            <x-slot:icon>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round"
                        d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4z" />
                </svg>
            </x-slot:icon>
        </x-dashboard.kpi-card>

        <x-dashboard.kpi-card data-kpi="approved" label="Approved" :value="$approved" :denominator="$total"
            sub="finished for this period" accent="green" :percent="$share($approved)">
            <x-slot:icon>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                </svg>
            </x-slot:icon>
        </x-dashboard.kpi-card>
    </div>

    <x-dashboard.head-records :records="$records" :head="$head" :period="$period" :unit="$unitName" />

    <x-dashboard.head-pending :pending="$pending" :head="$head" :period="$period" />
</section>
