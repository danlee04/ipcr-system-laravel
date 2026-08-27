<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\IpcrPeriod;
use App\Support\SummaryRow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The hospital's roll for one rating period.
 *
 * Built from the employees, not from the IPCRs. Those are two different
 * questions: a list of IPCRs says how the started ones are getting on, and
 * this says who is accounted for - which is what has to be handed in.
 */
class PeriodSummaryService
{
    /**
     * One row per active employee, in reading order.
     *
     * @return Collection<int, SummaryRow>
     */
    public function rows(IpcrPeriod $period, ?int $divisionId = null, ?int $sectionId = null): Collection
    {
        return Employee::query()
            ->active()
            ->with([
                'position', 'section', 'division',
                // Scoped to the one period, so each employee carries at most
                // the single IPCR this sheet is about.
                'ipcrs' => fn ($query) => $query->where('ipcr_period_id', $period->id),
            ])
            ->when($divisionId, fn (Builder $query, int $id) => $query->where('division_id', $id))
            ->when($sectionId, fn (Builder $query, int $id) => $query->where('section_id', $id))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (Employee $employee) use ($period): SummaryRow {
                $ipcr = $employee->ipcrs->first();

                // The period is already in hand. Handing it to the IPCR keeps
                // the lateness check from fetching the same row once per name.
                $ipcr?->setRelation('period', $period);

                return new SummaryRow($employee, $ipcr);
            });
    }

    /**
     * The same rows gathered into divisions and then sections.
     *
     * Ordered by name at both levels rather than by id: the sheet is read by
     * people looking for their own office, and creation order means nothing
     * to them.
     *
     * @param  Collection<int, SummaryRow>  $rows
     * @return Collection<string, Collection<string, Collection<int, SummaryRow>>>
     */
    public function gather(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (SummaryRow $row): string => $row->divisionName())
            ->sortKeys()
            ->map(fn (Collection $inDivision): Collection => $inDivision
                ->groupBy(fn (SummaryRow $row): string => $row->sectionName())
                ->sortKeys());
    }
}
