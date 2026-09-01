<?php

namespace App\Services;

use App\Enums\IpcrStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrPeriod;
use App\Support\DashboardScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Every figure on the admin dashboard, all of them answering to the same
 * period / division / section scope.
 *
 * A note on the status columns, because two of them are easy to confuse:
 *   review   = Submitted, sitting with the assessor
 *   final    = Assessed, sitting with the final approver
 *   returned = sent back to the employee, and NOT the same as `final`
 */
class DashboardStats
{
    /** @return array<string, int|float|null> */
    public function totals(DashboardScope $scope): array
    {
        $counts = $this->scoped($scope)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $of = fn (IpcrStatus $status): int => (int) ($counts[$status->value] ?? 0);

        $average = $this->scoped($scope)
            ->whereNotNull('final_numerical_rating')
            ->avg('final_numerical_rating');

        return [
            'total'    => (int) $counts->sum(),
            'draft'    => $of(IpcrStatus::Draft),
            'review'   => $of(IpcrStatus::Submitted),
            'final'    => $of(IpcrStatus::Assessed),
            'approved' => $of(IpcrStatus::Approved),
            'returned' => $of(IpcrStatus::Returned),

            'avg_rating' => $average === null ? null : round((float) $average, 2),

            // Which approval chain the IPCR travels. A section head's own IPCR
            // goes to their division head, everyone else's to their section head.
            'section_head_track' => $this->scoped($scope)->whereIn('employee_id', $this->sectionHeadIds())->count(),
            'employee_track'     => $this->scoped($scope)->whereNotIn('employee_id', $this->sectionHeadIds())->count(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function byDivision(DashboardScope $scope): array
    {
        $divisions = Division::query()->orderBy('name')->get();

        return $divisions->map(function (Division $division) use ($scope): array {
            // The division is fixed per row, so the scope's own division filter
            // is replaced rather than added - otherwise every other row is zero.
            $rowScope = new DashboardScope(
                periodId: $scope->periodId,
                divisionId: $division->id,
                sectionId: null,
            );

            return ['id' => $division->id, 'name' => $division->name] + $this->totals($rowScope);
        })->all();
    }

    /** @return list<array{id: int, label: string, count: int, approved: int}> */
    public function byPeriod(DashboardScope $scope): array
    {
        return IpcrPeriod::query()
            ->orderBy('start_date')
            ->get()
            ->map(function (IpcrPeriod $period) use ($scope): array {
                $rowScope = new DashboardScope(
                    periodId: $period->id,
                    divisionId: $scope->divisionId,
                    sectionId: $scope->sectionId,
                );

                return [
                    'id'       => $period->id,
                    'label'    => $period->name,
                    'count'    => $this->scoped($rowScope)->count(),
                    'approved' => $this->scoped($rowScope)->where('status', IpcrStatus::Approved)->count(),
                ];
            })
            ->all();
    }

    /** @return Collection<int, Ipcr> */
    public function recentSubmissions(DashboardScope $scope, int $limit = 8): Collection
    {
        return $this->scoped($scope)
            ->whereNotNull('submitted_at')
            ->with(['employee', 'period'])
            ->latest('submitted_at')
            ->limit($limit)
            ->get();
    }

    /**
     * The people a head looks after, and where each of them has got to.
     *
     * A section head sees their section; a division head sees every section
     * under them, however the employee is filed - some carry a division of
     * their own and some reach it through their section. The Chief sees the
     * hospital.
     *
     * The head is left off their own roster: their sheet is the card at the
     * top of the page and does not need a second row underneath it.
     *
     * @return Collection<int, array{employee: Employee, ipcr: ?Ipcr}>
     */
    public function teamOf(Employee $head, ?IpcrPeriod $period): Collection
    {
        $query = Employee::query()->active()->with(['position', 'section']);

        if ($section = $head->headedSection) {
            $this->postedToSection($query, $section->id)
                // Nobody above them. A division head has to be filed in some
                // section and the Chief has to sit somewhere, and their sheets
                // go upward - the Chief assesses a division head. A name on the
                // roster that cannot be chased is noise.
                ->whereDoesntHave('headedDivision')
                ->where('is_chief_of_hospital', false);
        } elseif ($division = $head->headedDivision) {
            // Everyone in the division, section heads included: the division
            // head assesses those and gives the final word on everyone else.
            $this->postedToDivision($query, $division->id)
                ->where('is_chief_of_hospital', false);
        } elseif (! $head->isChiefOfHospital()) {
            return collect();
        }

        $people = $query->whereKeyNot($head->id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        // One query for the sheets rather than one per person.
        $sheets = $period === null
            ? collect()
            : Ipcr::query()
                ->where('ipcr_period_id', $period->id)
                ->whereIn('employee_id', $people->pluck('id'))
                ->get()
                ->keyBy('employee_id');

        return $people->map(fn (Employee $person): array => [
            'employee' => $person,
            'ipcr'     => $sheets->get($person->id),
        ]);
    }

    /**
     * Whoever answers to this section.
     *
     * Three ways in, and the first that applies decides where somebody
     * belongs: they head the section, a designation posts them here, or they
     * are filed here and neither runs a unit nor is posted anywhere. Running a
     * unit beats a posting and a posting beats the plantilla position - the
     * head of a section is run by the division that section sits in, whatever
     * division their own position is filed under - so each later way in has to
     * leave out whoever an earlier one has already placed.
     */
    private function postedToSection(Builder $query, int $sectionId): Builder
    {
        return $query->where(fn (Builder $outer) => $outer
            ->whereHas('headedSection', fn (Builder $s) => $s->whereKey($sectionId))
            ->orWhere(fn (Builder $posted) => $this->runsNothing($posted)
                ->whereHas(
                    'activeDesignations',
                    fn (Builder $d) => $d->where('designations.section_id', $sectionId),
                ))
            ->orWhere(fn (Builder $own) => $this->runsNothing($own)
                ->whereDoesntHave('activeDesignations', $this->posting(...))
                ->where('section_id', $sectionId)));
    }

    /** The same three ways in, one level up. */
    private function postedToDivision(Builder $query, int $divisionId): Builder
    {
        return $query->where(fn (Builder $outer) => $outer
            ->whereHas('headedSection', fn (Builder $s) => $s->where('division_id', $divisionId))
            ->orWhereHas('headedDivision', fn (Builder $d) => $d->whereKey($divisionId))
            ->orWhere(fn (Builder $posted) => $this->runsNothing($posted)
                ->whereHas('activeDesignations', fn (Builder $d) => $d
                    ->where('designations.division_id', $divisionId)
                    ->orWhereHas('section', fn (Builder $s) => $s->where('division_id', $divisionId))))
            ->orWhere(fn (Builder $own) => $this->runsNothing($own)
                ->whereDoesntHave('activeDesignations', $this->posting(...))
                ->where(fn (Builder $filed) => $filed->where('division_id', $divisionId)
                    ->orWhereHas('section', fn (Builder $s) => $s->where('division_id', $divisionId)))));
    }

    /** They run no unit of their own, so a posting or their filing places them. */
    private function runsNothing(Builder $query): Builder
    {
        return $query->whereDoesntHave('headedSection')->whereDoesntHave('headedDivision');
    }

    /** A designation that names an office rather than only a title. */
    private function posting(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNotNull('designations.division_id')
            ->orWhereNotNull('designations.section_id'));
    }

    /**
     * Active employees who have not sent an IPCR for assessment.
     *
     * A draft does not count: it has never left the employee's hands, so its
     * owner still belongs on the list HR chases.
     *
     * @return Collection<int, Employee>
     */
    public function notSubmitted(DashboardScope $scope): Collection
    {
        $submittedEmployeeIds = $this->scoped($scope)
            ->whereIn('status', [IpcrStatus::Submitted, IpcrStatus::Assessed, IpcrStatus::Approved])
            ->pluck('employee_id');

        return Employee::query()
            ->active()
            ->with(['position', 'section', 'division', 'user'])
            ->when($scope->divisionId, fn (Builder $q, int $id) => $q->where('division_id', $id))
            ->when($scope->sectionId, fn (Builder $q, int $id) => $q->where('section_id', $id))
            ->whereNotIn('id', $submittedEmployeeIds)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /** A fresh query already narrowed to the scope. */
    private function scoped(DashboardScope $scope): Builder
    {
        return Ipcr::query()
            ->when($scope->periodId, fn (Builder $q, int $id) => $q->where('ipcr_period_id', $id))
            ->when(
                $scope->divisionId,
                fn (Builder $q, int $id) => $q->whereHas('employee', fn (Builder $e) => $e->where('division_id', $id))
            )
            ->when(
                $scope->sectionId,
                fn (Builder $q, int $id) => $q->whereHas('employee', fn (Builder $e) => $e->where('section_id', $id))
            );
    }

    /** Cached for the request: used twice in every totals() call. */
    private ?array $sectionHeadIds = null;

    private function sectionHeadIds(): array
    {
        return $this->sectionHeadIds ??= \App\Models\Section::query()
            ->whereNotNull('section_head_employee_id')
            ->pluck('section_head_employee_id')
            ->all();
    }
}
