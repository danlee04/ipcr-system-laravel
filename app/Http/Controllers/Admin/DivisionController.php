<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDivisionRequest;
use App\Http\Requests\Admin\UpdateDivisionRequest;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Services\OrgDeletionGuard;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Divisions per page. Lower than the other admin lists on purpose: each
     * row brings its sections with it, so ten divisions is already a long
     * page.
     */
    private const PER_PAGE = 10;

    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->value();
        $sectionId = $request->integer('section');

        // A section filter narrows both levels: this closure limits the
        // sections shown inside each division, and the query below limits
        // which divisions are shown at all.
        $sections = function ($query) use ($sectionId, $search): void {
            $query->when($sectionId, fn ($q) => $q->whereKey($sectionId))
                ->orderBy('name');
        };

        $divisions = Division::query()
            ->with(['sections' => $sections, 'sections.head', 'head'])
            ->when($search, $this->matching(...))
            ->when($request->integer('division'), fn ($q, int $id) => $q->whereKey($id))
            ->when($sectionId, fn ($q, int $id) => $q->whereHas('sections', fn ($s) => $s->whereKey($id)))
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $reports = $divisions->mapWithKeys(
            fn (Division $division) => [$division->id => $this->guard->for($division)]
        );

        // getCollection(), not the paginator: the higher-order proxy below is
        // a property access, and a paginator has no such property.
        $sectionReports = $divisions->getCollection()
            ->flatMap->sections
            ->mapWithKeys(fn ($section) => [$section->id => $this->guard->for($section)]);

        // Candidates for the head select. Empty on a fresh database with no
        // employees, which is why employee management is the next phase.
        $employees = Employee::query()
            ->active()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('admin.divisions.index', compact('divisions', 'reports', 'sectionReports', 'employees') + [
            // The whole hospital, for the filter dropdowns and the New Section
            // form. Feeding those the paged list would shrink them to whatever
            // happened to be on screen, so a division on page 2 could never be
            // given a section from page 1.
            'allDivisions' => Division::query()->orderBy('name')->get(),
            'allSections'  => Section::query()->with('division')->orderBy('name')->get(),
        ]);
    }

    /**
     * Matches a division by its own name or code, or by one of its sections.
     *
     * A section brings its division with it: the page is a tree, and a hit
     * with nothing above it would have nowhere to be drawn.
     */
    private function matching(Builder $query, string $term): Builder
    {
        $like = '%' . $term . '%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('name', 'like', $like)
                ->orWhere('code', 'like', $like)
                ->orWhereHas('sections', fn (Builder $section) => $section
                    ->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like));
        });
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
