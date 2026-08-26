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
    public function recentActivity(DashboardScope $scope, int $limit = 6): Collection
    {
        return $this->scoped($scope)
            ->with(['employee', 'period'])
            ->latest('updated_at')
            ->limit($limit)
            ->get();
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
