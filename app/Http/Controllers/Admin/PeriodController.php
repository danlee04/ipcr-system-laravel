<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePeriodRequest;
use App\Http\Requests\Admin\UpdatePeriodRequest;
use App\Models\IpcrPeriod;
use App\Services\OrgDeletionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // The one every IPCR is created against. There is only ever one, so
        // the screen can simply name it rather than explain a choice it made.
        $activePeriod = IpcrPeriod::active();

        return view('admin.periods.index', compact('periods', 'reports', 'activePeriod'));
    }

    public function store(StorePeriodRequest $request): RedirectResponse
    {
        $period = DB::transaction(function () use ($request): IpcrPeriod {
            $period = IpcrPeriod::create($request->validated() + ['status' => 'open']);

            $this->makeTheOnlyActiveOne($period);

            return $period;
        });

        return redirect()->route('admin.periods.index')
            ->with('status', "\"{$period->name}\" is now the active rating period.");
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

        DB::transaction(function () use ($period, $validated): void {
            $period->update(['status' => $validated['open'] ? 'open' : 'closed']);

            if ($validated['open']) {
                $this->makeTheOnlyActiveOne($period);
            }
        });

        return redirect()->route('admin.periods.index')->with(
            'status',
            $validated['open']
                ? "\"{$period->name}\" is now the active rating period."
                : "Closed \"{$period->name}\". No rating period is active until you choose one."
        );
    }

    /**
     * Exactly one period is active.
     *
     * Every IPCR is created against it, and nothing named it before: any
     * number could sit open and the code quietly took the latest by start
     * date, which two periods starting on the same day turned into a coin
     * toss. Choosing one now closes the rest.
     */
    private function makeTheOnlyActiveOne(IpcrPeriod $period): void
    {
        IpcrPeriod::query()
            ->open()
            ->whereKeyNot($period->id)
            ->update(['status' => 'closed']);
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
