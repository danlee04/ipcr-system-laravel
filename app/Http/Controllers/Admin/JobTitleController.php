<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\Position;
use App\Services\OrgDeletionGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Positions and designations on one page, as two tabs.
 *
 * They share a page because an administrator thinks of both as "job titles you
 * assign to people". They stay separate models because they mean different
 * things: a position is the single plantilla post and the source of CORE
 * functions; a designation is an extra assignment an employee may hold several
 * of, and is the source of STRATEGIC and SUPPORT functions.
 */
class JobTitleController extends Controller
{
    public function __construct(private readonly OrgDeletionGuard $guard) {}

    /** Rows per page, matching the other admin lists. */
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'designations' ? 'designations' : 'positions';
        $search = $request->string('search')->trim()->value();

        // The search applies only to the tab being looked at, so switching tabs
        // does not silently carry a filter that made sense for the other one.
        $positions = Position::query()
            ->when($tab === 'positions' && $search, fn ($q) => $this->matching($q, $search, ['title', 'item_number']))
            ->orderBy('title')
            ->paginate(self::PER_PAGE, ['*'], 'page')
            ->withQueryString();

        $designations = Designation::query()
            ->when($tab === 'designations' && $search, fn ($q) => $this->matching($q, $search, ['title']))
            ->orderBy('title')
            ->paginate(self::PER_PAGE, ['*'], 'page')
            ->withQueryString();

        // The tab labels describe the whole set. Narrowing them to the search
        // would make the inactive tab look empty when it is not.
        $positionCount = Position::query()->count();
        $designationCount = Designation::query()->count();

        // Both maps are built regardless of the active tab. They are cheap, and
        // building only one would make the view's contract depend on the tab.
        $positionReports = $positions->mapWithKeys(
            fn (Position $position) => [$position->id => $this->guard->for($position)]
        );

        $designationReports = $designations->mapWithKeys(
            fn (Designation $designation) => [$designation->id => $this->guard->for($designation)]
        );

        return view('admin.job-titles.index', compact(
            'tab', 'positions', 'designations', 'positionReports', 'designationReports',
            'positionCount', 'designationCount', 'search'
        ));
    }

    /** Every word in the term must appear in one of the given columns. */
    private function matching(Builder $query, string $term, array $columns): Builder
    {
        foreach (preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $like = '%' . $word . '%';

            $query->where(function (Builder $inner) use ($like, $columns): void {
                foreach ($columns as $column) {
                    $inner->orWhere($column, 'like', $like);
                }
            });
        }

        return $query;
    }
}
