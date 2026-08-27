<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IpcrStatus;
use App\Http\Controllers\Concerns\RendersLiveLists;
use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrPeriod;
use App\Models\Section;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Every IPCR in the hospital, for HR and administrators.
 *
 * Read-only. This is the screen the dashboard drills into: it reports that
 * three people have not submitted and that a division has two drafts, and
 * without a list there is no way to reach any of them.
 *
 * Acting on an IPCR still belongs to the people in its approval chain -
 * IpcrPolicy grants the admin roles `view` and nothing else.
 */
class IpcrController extends Controller
{
    use RendersLiveLists;

    /** Rows per page, matching the other admin lists. */
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $status = IpcrStatus::tryFrom((string) $request->query('status'));

        $ipcrs = Ipcr::query()
            ->with(['employee.position', 'employee.section', 'employee.division', 'period', 'assessor', 'finalApprover'])
            ->when($status, fn (Builder $q) => $q->where('status', $status))
            ->when($request->integer('period'), fn (Builder $q, int $id) => $q->where('ipcr_period_id', $id))
            ->when(
                $request->integer('division'),
                fn (Builder $q, int $id) => $q->whereHas('employee', fn (Builder $e) => $e->where('division_id', $id))
            )
            ->when(
                $request->integer('section'),
                fn (Builder $q, int $id) => $q->whereHas('employee', fn (Builder $e) => $e->where('section_id', $id))
            )
            ->when($request->string('search')->trim()->value(), $this->searchFor(...))
            ->latest('updated_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return $this->liveList($request, 'admin.ipcrs.index', 'admin.ipcrs.rows', [
            'ipcrs'      => $ipcrs,
            'periods'    => IpcrPeriod::query()->orderByDesc('start_date')->get(),
            'divisions'  => Division::query()->orderBy('name')->get(),
            'sections'   => Section::query()->orderBy('name')->get(),
            'statuses'   => IpcrStatus::cases(),

            // Backs the approver pickers. Active only: naming a retired
            // employee as assessor would route the IPCR into a dead account.
            'employees'  => Employee::query()->active()
                ->orderBy('last_name')->orderBy('first_name')->get(),
        ]);
    }

    /**
     * Matched against the employee's name and number.
     *
     * Each word must match, so "maria santos" finds a Maria Santos whose names
     * live in two columns. No SQL concatenation: `||` means OR in MySQL and
     * concat in SQLite, and this app runs on both.
     */
    private function searchFor(Builder $query, string $term): Builder
    {
        foreach (preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $like = '%' . $word . '%';

            $query->whereHas('employee', fn (Builder $employee) => $employee->where(
                fn (Builder $inner) => $inner->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('middle_name', 'like', $like)
                    ->orWhere('employee_number', 'like', $like)
            ));
        }

        return $query;
    }
}
