<?php

namespace App\Http\Controllers;

use App\Enums\FunctionCategory;
use App\Enums\IpcrMode;
use App\Enums\IpcrStatus;
use App\Exceptions\IpcrRoutingException;
use App\Models\Ipcr;
use App\Models\IpcrPeriod;
use App\Notifications\IpcrSubmitted;
use App\Services\FunctionCatalogService;
use App\Services\IpcrRoutingService;
use App\Services\ItemWeights;
use App\Services\RatingCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IpcrController extends Controller
{
    public function __construct(
        private readonly FunctionCatalogService $functionCatalog,
        private readonly IpcrRoutingService $routing,
        private readonly RatingCalculator $ratings,
        private readonly ItemWeights $weights,
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

        $period = IpcrPeriod::active();

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

        $period = IpcrPeriod::active();
        abort_unless($period, 404, 'No open rating period found.');

        $ipcr = Ipcr::firstOrCreate(
            ['employee_id' => $employee->id, 'ipcr_period_id' => $period->id],
            [
                'position_title' => $employee->position?->title,
                'office_name'    => $employee->officeName(),
                'status'         => IpcrStatus::Draft,
                'mode'           => $mode,
            ]
        );

        return redirect()->route('ipcrs.show', $ipcr)
            ->with('status', 'Draft IPCR created. Add your functions below.');
    }

    /**
     * Scrap an IPCR. The policy decides whose, and at what stage.
     *
     * Its lines and its approval history go with it through the cascade. That
     * is the whole point and also the whole risk, which is why an approved one
     * is out of reach until somebody reopens it.
     *
     * Back where it was deleted from, not always to My IPCRs: an administrator
     * clearing test records off the hospital-wide list should stay on it.
     */
    public function destroy(Request $request, Ipcr $ipcr): RedirectResponse
    {
        $this->authorize('delete', $ipcr);

        $owner = $ipcr->employee?->full_name;
        $periodName = $ipcr->period?->name;

        $ipcr->delete();

        $said = $request->user()->id === $ipcr->employee?->user_id
            ? "Deleted your IPCR for {$periodName}."
            : "Deleted {$owner}'s IPCR for {$periodName}.";

        return back()->with('status', $said);
    }

    /** The main working screen: view items, add/edit them, and submit. */
    public function show(Request $request, Ipcr $ipcr): View
    {
        $this->authorize('view', $ipcr);

        // The rubric comes with each line: it is what the report form asks
        // for, and loading it here keeps twenty lines from being twenty
        // queries deep.
        $ipcr->load([
            'items.measures', 'items.jobFunction.measures.bands',
            'period', 'employee', 'assessor', 'finalApprover',
            'approvals.approver', 'approvals.actedBy',
        ]);
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

        // Core, then Support, then Strategic - the same order as the screen.
        // A printed sheet that reads in a different order from the one it was
        // built in is a sheet nobody can check line by line.
        $grouped = collect([
            FunctionCategory::Core,
            FunctionCategory::Support,
            FunctionCategory::Strategic,
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

        // The marks are the employee's to give, so the last moment anyone can
        // give them is here. An approver who received an unmarked line could
        // neither mark it nor return it to any purpose - there is no form on
        // their side to fix it with.
        if ($ipcr->showsAccomplishment()) {
            $unmarked = $ipcr->load('items')->items
                ->filter(fn ($item): bool => $item->average_rating === null)
                ->count();

            if ($unmarked > 0) {
                return back()->with('error', $unmarked === 1
                    ? 'One function still has no rating. Open it and give yourself a mark on at least one measure.'
                    : "{$unmarked} functions still have no rating. Open each one and give yourself a mark on at least one measure.");
            }
        }

        // Settled rather than checked. The weights are shared out by the
        // system, so a category that does not total a hundred is this app's
        // mistake and not the employee's - and refusing to submit over one
        // would hand them an error about a field they cannot even see.
        $this->weights->shareAll($ipcr);

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

            $ipcr->assessor?->user?->notify(new IpcrSubmitted($ipcr->fresh()));

            return redirect()->route('ipcrs.show', $ipcr)
                ->with('status', $this->submittedMessage($ipcr, $ipcr->assessor->full_name));
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

        // Only the person it now waits on. Everyone else has nothing to do
        // with it yet, and a notification they cannot act on is noise.
        $chain->assessor->user?->notify(new IpcrSubmitted($ipcr->fresh()));

        return redirect()->route('ipcrs.show', $ipcr)
            ->with('status', $this->submittedMessage($ipcr, $chain->assessor->full_name));
    }

    /**
     * What the owner is told the moment they submit.
     *
     * A late submission is accepted - blocking one in a hospital turns into a
     * phone call to the administrator - but it is never accepted quietly. The
     * person who was late should hear it here, not find out weeks later on
     * somebody else's report.
     */
    private function submittedMessage(Ipcr $ipcr, string $assessorName): string
    {
        $message = "Submitted for assessment to {$assessorName}.";

        $ipcr = $ipcr->fresh();

        if ($ipcr->isLate()) {
            $days = $ipcr->daysLate();
            $message .= ' This was ' . $days . ' ' . Str::plural('day', $days) . ' past the deadline.';
        }

        return $message;
    }
}
