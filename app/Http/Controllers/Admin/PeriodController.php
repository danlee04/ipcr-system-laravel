<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePeriodRequest;
use App\Http\Requests\Admin\UpdatePeriodRequest;
use App\Models\IpcrPeriod;
use App\Services\OrgDeletionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Rating periods - the cycle every IPCR belongs to.
 *
 * Opening and closing a period is the only control over when employees may
 * start an IPCR at all: IpcrController offers the "New IPCR" button solely
 * when IpcrPeriod::open() returns something.
 */
class PeriodController extends Controller
{
    public function __construct(private readonly OrgDeletionGuard $guard) {}

    public function index(): View
    {
        $periods = IpcrPeriod::query()
            ->withCount('ipcrs')
            ->orderByDesc('year')
            ->orderByDesc('start_date')
            ->get();

        $reports = $periods->mapWithKeys(
            fn (IpcrPeriod $period) => [$period->id => $this->guard->for($period)]
        );

        // IpcrController takes the LATEST open period. With more than one open
        // that choice is invisible to the administrator, so name it here.
        $openPeriods = $periods->where('status', 'open');
        $effectivePeriod = $openPeriods->sortByDesc('start_date')->first();

        return view('admin.periods.index', compact('periods', 'reports', 'openPeriods', 'effectivePeriod'));
    }

    public function store(StorePeriodRequest $request): RedirectResponse
    {
        $period = IpcrPeriod::create($request->validated() + ['status' => 'open']);

        return redirect()->route('admin.periods.index')
            ->with('status', "Opened rating period \"{$period->name}\".");
    }

    public function update(UpdatePeriodRequest $request, IpcrPeriod $period): RedirectResponse
    {
        $period->update($request->validated());

        return redirect()->route('admin.periods.index')
            ->with('status', "Updated rating period \"{$period->name}\".");
    }

    public function setStatus(Request $request, IpcrPeriod $period): RedirectResponse
    {
        // An explicit value, not a toggle: two tabs disagreeing about the
        // current state would otherwise flip it the wrong way.
        $validated = $request->validate(['open' => ['required', 'boolean']]);

        $period->update(['status' => $validated['open'] ? 'open' : 'closed']);

        return redirect()->route('admin.periods.index')->with(
            'status',
            ($validated['open'] ? 'Reopened' : 'Closed') . " rating period \"{$period->name}\"."
        );
    }

    public function destroy(IpcrPeriod $period): RedirectResponse
    {
        // Re-checked here, not just in the view: a stale tab could otherwise
        // delete a period that gained an IPCR in the meantime.
        $report = $this->guard->for($period);

        if (! $report->deletable) {
            return redirect()->route('admin.periods.index')->with('error', $report->message());
        }

        $name = $period->name;
        $period->delete();

        return redirect()->route('admin.periods.index')
            ->with('status', "Deleted rating period \"{$name}\".");
    }
}
