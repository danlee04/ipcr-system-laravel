<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDivisionRequest;
use App\Http\Requests\Admin\UpdateDivisionRequest;
use App\Models\Division;
use App\Models\Employee;
use App\Services\OrgDeletionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Divisions - the top organizational unit below the Chief of Hospital.
 *
 * The head assignment on this screen is not decoration. IpcrRoutingService
 * reads divisions.division_head_employee_id to resolve an approval chain, and
 * until it is set nobody in the division can submit an IPCR at all.
 */
class DivisionController extends Controller
{
    public function __construct(private readonly OrgDeletionGuard $guard) {}

    public function index(): View
    {
        $divisions = Division::query()
            ->with(['sections' => fn ($q) => $q->orderBy('name'), 'sections.head', 'head'])
            ->orderBy('name')
            ->get();

        $reports = $divisions->mapWithKeys(
            fn (Division $division) => [$division->id => $this->guard->for($division)]
        );

        $sectionReports = $divisions
            ->flatMap->sections
            ->mapWithKeys(fn ($section) => [$section->id => $this->guard->for($section)]);

        // Candidates for the head select. Empty on a fresh database with no
        // employees, which is why employee management is the next phase.
        $employees = Employee::query()
            ->active()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('admin.divisions.index', compact('divisions', 'reports', 'sectionReports', 'employees'));
    }

    public function store(StoreDivisionRequest $request): RedirectResponse
    {
        $division = Division::create($request->validated() + ['is_active' => true]);

        return redirect()->route('admin.divisions.index')
            ->with('status', "Created division \"{$division->name}\".");
    }

    public function update(UpdateDivisionRequest $request, Division $division): RedirectResponse
    {
        $division->update($request->validated());

        return redirect()->route('admin.divisions.index')
            ->with('status', "Updated division \"{$division->name}\".");
    }

    public function setActive(Request $request, Division $division): RedirectResponse
    {
        // An explicit value, not a toggle: two tabs disagreeing about the
        // current state would otherwise flip it the wrong way.
        $validated = $request->validate(['active' => ['required', 'boolean']]);

        $division->update(['is_active' => $validated['active']]);

        return redirect()->route('admin.divisions.index')->with(
            'status',
            ($validated['active'] ? 'Activated' : 'Deactivated') . " division \"{$division->name}\"."
        );
    }

    public function destroy(Division $division): RedirectResponse
    {
        // Re-checked here, not just in the view: a stale tab could otherwise
        // delete a record that gained a reference in the meantime.
        $report = $this->guard->for($division);

        if (! $report->deletable) {
            return redirect()->route('admin.divisions.index')->with('error', $report->message());
        }

        $name = $division->name;
        $division->delete();

        return redirect()->route('admin.divisions.index')
            ->with('status', "Deleted division \"{$name}\".");
    }
}
