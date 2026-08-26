<?php

namespace App\Http\Controllers;

use App\Enums\FunctionCategory;
use App\Enums\IpcrMode;
use App\Enums\IpcrStatus;
use App\Exceptions\IpcrRoutingException;
use App\Models\Ipcr;
use App\Models\IpcrPeriod;
use App\Services\FunctionCatalogService;
use App\Services\IpcrRoutingService;
use App\Services\RatingCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IpcrController extends Controller
{
    public function __construct(
        private readonly FunctionCatalogService $functionCatalog,
        private readonly IpcrRoutingService $routing,
        private readonly RatingCalculator $ratings,
    ) {}

    /**
     * List of the current user's own IPCRs.
     *
     * A new IPCR is also started from here, so the page needs to know the
     * same two things create() knows: is a period open, and does the employee
     * already have an IPCR for it. Without that the modal would open, the user
     * would choose, and only then would they hit a failure - worse than not
     * being offered the option at all.
     */
    public function index(Request $request): View
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'No employee record is linked to your account.');

        $ipcrs = $employee->ipcrs()->with('period')->latest('id')->paginate(10);

        $period = IpcrPeriod::open()->latest('start_date')->first();

        $existingForPeriod = $period === null
            ? null
            : $employee->ipcrs()->where('ipcr_period_id', $period->id)->first();

        $canCreate = $period !== null && $existingForPeriod === null;
        $catalog = $canCreate ? $this->functionCatalog->availableFor($employee) : null;

        return view('ipcrs.index', compact(
            'ipcrs',
            'period',
            'existingForPeriod',
            'canCreate',
            'catalog',
        ));
    }

    /** Show the form to start a new IPCR for the current open rating period. */
    public function create(Request $request): View|RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'No employee record is linked to your account.');

        $period = IpcrPeriod::open()->latest('start_date')->first();

        if ($period === null) {
            return back()->with('error', 'There is no open rating period right now. Contact HR/Admin.');
        }

        if ($employee->ipcrs()->where('ipcr_period_id', $period->id)->exists()) {
            return redirect()->route('ipcrs.index')->with('error', 'You already have an IPCR for the current period.');
        }

        $catalog = $this->functionCatalog->availableFor($employee);

        return view('ipcrs.create', compact('period', 'catalog'));
    }

    /**
     * Create the draft IPCR header. No items yet - those are added
     * afterward through IpcrItemController on the "show" screen.
     */
    public function store(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'No employee record is linked to your account.');

        // The modal on the list is what picks the mode. The create page no
        // longer asks, so this is optional: with nothing given we use Targets
        // only, the safer default because it demands no accomplishment before
        // the IPCR can be submitted.
        $validated = $request->validate([
            'mode' => ['nullable', Rule::enum(IpcrMode::class)],
        ]);

        $mode = $validated['mode'] ?? IpcrMode::TargetsOnly->value;

        $period = IpcrPeriod::open()->latest('start_date')->first();
        abort_unless($period, 404, 'No open rating period found.');

        $ipcr = Ipcr::firstOrCreate(
            ['employee_id' => $employee->id, 'ipcr_period_id' => $period->id],
            [
                'position_title' => $employee->position?->title,
                'office_name'    => $employee->section?->name ?? $employee->division?->name,
                'status'         => IpcrStatus::Draft,
                'mode'           => $mode,
            ]
        );

        return redirect()->route('ipcrs.show', $ipcr)
            ->with('status', 'Draft IPCR created. Add your functions below.');
    }

    /**
     * Scrap a draft the owner no longer wants.
     *
     * The policy allows drafts only. The IPCR's items go with it through the
     * cascade, which is right - they exist only inside this draft. No
     * ipcr_approvals are destroyed: a draft has never been passed to anyone,
     * so no action can have been recorded against it.
     */
    public function destroy(Request $request, Ipcr $ipcr): RedirectResponse
    {
        $this->authorize('delete', $ipcr);

        $periodName = $ipcr->period->name;

        $ipcr->delete();

        return redirect()->route('ipcrs.index')
            ->with('status', "Deleted your draft IPCR for {$periodName}.");
    }

    /** The main working screen: view items, add/edit them, and submit. */
    public function show(Request $request, Ipcr $ipcr): View
    {
        $this->authorize('view', $ipcr);

        $ipcr->load(['items', 'period', 'employee', 'assessor', 'finalApprover', 'approvals.approver', 'approvals.actedBy']);
        $catalog = $this->functionCatalog->availableFor($ipcr->employee);

        return view('ipcrs.show', compact('ipcr', 'catalog'));
    }

    /**
     * The printable sheet.
     *
     * Guarded by `view`, so the owner, both approvers, and HR or an
     * administrator can produce it. Rendered on its own layout with no sidebar
     * or navigation: this is the sheet that gets signed and filed, and the
     * signatures are what make it the record rather than the database row.
     */
    public function print(Request $request, Ipcr $ipcr): View
    {
        $this->authorize('view', $ipcr);

        $ipcr->load(['items', 'period', 'employee.position', 'assessor', 'finalApprover']);

        // Grouped in reading order, which is the order the CSC form uses.
        $grouped = collect([
            FunctionCategory::Strategic,
            FunctionCategory::Core,
            FunctionCategory::Support,
        ])->mapWithKeys(fn (FunctionCategory $category): array => [
            $category->value => $ipcr->items->where('category', $category)->sortBy('sort_order')->values(),
        ])->filter(fn ($items) => $items->isNotEmpty());

        $breakdown = $this->ratings->for($ipcr);

        return view('ipcrs.print', compact('ipcr', 'grouped', 'breakdown'));
    }

    /**
     * Submit the IPCR: resolve the approval chain via IpcrRoutingService,
     * stamp the assessor/final approver on the record, and move the
     * status to "submitted" (shown to users as "For Assessment").
     */
    public function submit(Request $request, Ipcr $ipcr): RedirectResponse
    {
        $this->authorize('update', $ipcr);

        if (! $ipcr->isEditableByOwner()) {
            return back()->with('error', 'This IPCR can no longer be submitted from its current status.');
        }

        if ($ipcr->items()->doesntExist()) {
            return back()->with('error', 'Add at least one function before submitting.');
        }

        // Checked before routing: if the owner chose "with accomplishments",
        // that choice means nothing while an item is still blank.
        $missing = $ipcr->itemsMissingAccomplishment();

        if ($missing > 0) {
            return back()->with('error', $missing === 1
                ? 'One function still has no actual accomplishment. Fill it in, or switch to "Targets only".'
                : "{$missing} functions still have no actual accomplishment. Fill them in, or switch to \"Targets only\".");
        }

        // The weights must add up before anyone is asked to assess this. The
        // rating maths would cope with any total, but the CSC form does not,
        // and this is the last moment the owner can still fix it.
        $badTotals = $ipcr->load('items')->categoriesWithBadWeightTotals();

        if ($badTotals !== []) {
            $parts = [];

            foreach ($badTotals as $category => $total) {
                $label = FunctionCategory::from($category)->label();
                $parts[] = "{$label} totals " . rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.') . '%';
            }

            return back()->with(
                'error',
                'Weights must total 100% in each category. ' . implode('; ', $parts) . '.'
            );
        }

        // A chain HR or an administrator set by hand wins over the org chart.
        // It is the only way the Chief of Hospital's own IPCR moves at all -
        // IpcrRoutingService refuses to route them, having nobody above them.
        // A chain merely left over from an earlier submission does not count:
        // that one is resolved again, so a change of head is picked up.
        if ($ipcr->hasOverriddenChain()) {
            $ipcr->update([
                'status'       => IpcrStatus::Submitted,
                'submitted_at' => now(),
            ]);

            return redirect()->route('ipcrs.show', $ipcr)
                ->with('status', "Submitted for assessment to {$ipcr->assessor->full_name}.");
        }

        try {
            $chain = $this->routing->resolve($ipcr->employee);
        } catch (IpcrRoutingException $e) {
            return back()->with('error', $e->getMessage());
        }

        $ipcr->update([
            'assessor_employee_id'       => $chain->assessor->id,
            'final_approver_employee_id' => $chain->finalApprover->id,
            'status'                     => IpcrStatus::Submitted,
            'submitted_at'               => now(),
        ]);

        return redirect()->route('ipcrs.show', $ipcr)
            ->with('status', "Submitted for assessment to {$chain->assessor->full_name}.");
    }
}
