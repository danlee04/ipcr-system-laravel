<?php

namespace App\Http\Controllers;

use App\Enums\IpcrStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrPeriod;
use App\Models\Section;
use App\Services\DashboardStats;
use App\Services\SetupHealth;
use App\Support\DashboardScope;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The landing page. It answers one question per person: what should I do next?
 *
 * Three audiences share it, and each part appears only for whoever it applies
 * to - an administrator with no employee record gets no personal IPCR card,
 * someone who approves nothing gets no approval card, and the hospital-wide
 * figures are for HR and administrators only.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly SetupHealth $setupHealth,
        private readonly DashboardStats $stats,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $employee = $user->employee;
        $isAdmin = $user->hasAnyRole(['admin', 'hr']);

        $period = IpcrPeriod::open()->latest('start_date')->first();

        return view('dashboard', [
            'employee' => $employee,
            'period'   => $period,
            'myIpcr'   => $this->currentIpcr($employee, $period),
            'pending'  => $this->pendingCounts($employee),
            'admin'    => $isAdmin ? $this->adminSummary($request, $period) : null,
        ]);
    }

    /** The employee's IPCR for the open period, if they have started one. */
    private function currentIpcr(?Employee $employee, ?IpcrPeriod $period): ?Ipcr
    {
        if ($employee === null || $period === null) {
            return null;
        }

        return $employee->ipcrs()->where('ipcr_period_id', $period->id)->first();
    }

    /** @return array{assessment: int, final: int, total: int} */
    private function pendingCounts(?Employee $employee): array
    {
        if ($employee === null) {
            return ['assessment' => 0, 'final' => 0, 'total' => 0];
        }

        $assessment = Ipcr::query()->awaitingAssessmentBy($employee)->count();
        $final = Ipcr::query()->awaitingFinalRatingBy($employee)->count();

        return [
            'assessment' => $assessment,
            'final'      => $final,
            'total'      => $assessment + $final,
        ];
    }

    private function adminSummary(Request $request, ?IpcrPeriod $period): array
    {
        $scope = DashboardScope::fromRequest($request);

        $divisionStats = $this->stats->byDivision($scope);

        return [
            'scope'    => $scope,
            'problems' => $this->setupHealth->problems(),

            'totals'        => $this->stats->totals($scope),
            'divisionStats' => $divisionStats,
            'periodStats'   => $this->stats->byPeriod($scope),
            'recent'        => $this->stats->recentActivity($scope),
            'notSubmitted'  => $scope->isFiltered() ? $this->stats->notSubmitted($scope) : collect(),

            // Submission progress against the currently open period, which is
            // what HR chases regardless of what the filters are showing.
            'submitted' => $period === null ? 0 : Ipcr::query()
                ->where('ipcr_period_id', $period->id)
                ->whereIn('status', [IpcrStatus::Submitted, IpcrStatus::Assessed, IpcrStatus::Approved])
                ->distinct()
                ->count('employee_id'),
            'expected' => Employee::query()->active()->count(),

            // Filter options.
            'periods'    => IpcrPeriod::query()->orderByDesc('start_date')->get(),
            'divisions'  => Division::query()->orderBy('name')->get(),
            'sections'   => Section::query()->orderBy('name')->get(),
            'currentPeriodId' => $period?->id,

            // Keyed by id for the client-side swap when a division row is clicked.
            'divisionMap' => collect($divisionStats)->keyBy('id'),
        ];
    }
}
