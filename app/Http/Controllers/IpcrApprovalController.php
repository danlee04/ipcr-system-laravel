<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalStage;
use App\Enums\IpcrStatus;
use App\Enums\RatingMeasure;
use App\Models\Ipcr;
use App\Services\RatingCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The approval side of an IPCR: Submitted -> Assessed -> Approved.
 *
 * The assessor enters the Q/E/T marks; the final approver confirms them. That
 * is why rating lives here and not on IpcrController, whose `update` ability
 * belongs to the owner alone.
 */
class IpcrApprovalController extends Controller
{
    public function __construct(private readonly RatingCalculator $calculator) {}

    /** Everything waiting on the signed-in approver, in both stages. */
    public function inbox(Request $request): View
    {
        $approver = $request->user()->employee;
        abort_unless($approver, 403, 'No employee record is linked to your account.');

        $forAssessment = Ipcr::query()
            ->awaitingAssessmentBy($approver)
            ->with(['employee', 'period'])
            ->orderBy('submitted_at')
            ->get();

        $forFinalRating = Ipcr::query()
            ->awaitingFinalRatingBy($approver)
            ->with(['employee', 'period'])
            ->orderBy('assessed_at')
            ->get();

        return view('approvals.inbox', compact('forAssessment', 'forFinalRating'));
    }

    /**
     * Save the marks against each line.
     *
     * Partial saves are allowed on purpose: an assessor rating twenty lines
     * should not lose the work because they have not finished. The check that
     * every line is rated happens at assess(), not here.
     *
     * A measure the catalog rubric grades is skipped: it belongs to the figure
     * the employee reported, not to whoever is looking at the form. An
     * assessor who disagrees with a figure returns the IPCR for revision.
     */
    public function updateRatings(Request $request, Ipcr $ipcr): RedirectResponse
    {
        $this->authorize('assess', $ipcr);

        // Restricting the ids to this IPCR's own items is what stops a crafted
        // form from writing marks onto somebody else's record.
        $ownItemIds = $ipcr->items()->pluck('id')->all();

        $validated = $request->validate([
            'ratings' => ['required', 'array', $this->onlyOwnItems($ownItemIds)],
            'ratings.*' => ['array'],
            'ratings.*.quality' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'ratings.*.efficiency' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'ratings.*.timeliness' => ['nullable', 'numeric', 'min:1', 'max:5'],
        ]);

        DB::transaction(function () use ($validated, $ipcr): void {
            foreach ($validated['ratings'] as $itemId => $marks) {
                $item = $ipcr->items()->with('jobFunction.measures')->find($itemId);

                if ($item === null) {
                    continue;
                }

                $graded = $item->rubricMeasures();
                $fields = [];

                foreach (RatingMeasure::cases() as $measure) {
                    if (in_array($measure, $graded, true)) {
                        continue;
                    }

                    $fields[$measure->column()] = $marks[$measure->value] ?? null;
                }

                // The average follows from the marks, and IpcrItem recomputes
                // it on every save - including the ones the rubric set.
                $item->update($fields);
            }
        });

        return redirect()->route('ipcrs.show', $ipcr)->with('status', 'Ratings saved.');
    }

    /** Finish the assessment and pass the IPCR to the final approver. */
    public function assess(Request $request, Ipcr $ipcr): RedirectResponse
    {
        $this->authorize('assess', $ipcr);

        $breakdown = $this->calculator->for($ipcr->load('items'));

        if (! $breakdown->complete) {
            return back()->with('error', $ipcr->items()->doesntExist()
                ? 'This IPCR has no functions to rate.'
                : "{$breakdown->unratedItemCount} function(s) have no mark at all. Each line needs at least one of quality, efficiency or timeliness - leave a measure blank where it does not apply.");
        }

        DB::transaction(function () use ($ipcr, $breakdown, $request): void {
            $ipcr->update($breakdown->toIpcrColumns() + [
                'status'      => IpcrStatus::Assessed,
                'assessed_at' => now(),
            ]);

            $this->record($ipcr, $request, ApprovalStage::Assessment, ApprovalAction::Approved);
        });

        return redirect()->route('approvals.inbox')->with(
            'status',
            "Assessment complete for {$ipcr->employee->full_name}. Sent to {$ipcr->finalApprover?->full_name} for final approval."
        );
    }

    /** Give the final approval. This is the last step. */
    public function approve(Request $request, Ipcr $ipcr): RedirectResponse
    {
        $this->authorize('finalize', $ipcr);

        // Recomputed rather than trusted: the columns were written at
        // assessment time, and this is the number that becomes permanent.
        $breakdown = $this->calculator->for($ipcr->load('items'));

        if (! $breakdown->complete) {
            return back()->with('error', 'This IPCR is not fully rated and cannot be approved.');
        }

        DB::transaction(function () use ($ipcr, $breakdown, $request): void {
            $ipcr->update($breakdown->toIpcrColumns() + [
                'status'      => IpcrStatus::Approved,
                'approved_at' => now(),
            ]);

            $this->record($ipcr, $request, ApprovalStage::FinalRating, ApprovalAction::Approved);
        });

        return redirect()->route('approvals.inbox')->with(
            'status',
            "Approved {$ipcr->employee->full_name}'s IPCR — {$breakdown->finalNumeric} ({$breakdown->finalAdjectival->value})."
        );
    }

    /** Send it back to the owner. Remarks are required: they must know why. */
    public function returnForRevision(Request $request, Ipcr $ipcr): RedirectResponse
    {
        $this->authorize('returnForRevision', $ipcr);

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:2000'],
        ]);

        $stage = $ipcr->status === IpcrStatus::Submitted
            ? ApprovalStage::Assessment
            : ApprovalStage::FinalRating;

        DB::transaction(function () use ($ipcr, $request, $stage, $validated): void {
            $ipcr->update(['status' => IpcrStatus::Returned]);

            $this->record($ipcr, $request, $stage, ApprovalAction::Returned, $validated['remarks']);
        });

        return redirect()->route('approvals.inbox')
            ->with('status', "Returned {$ipcr->employee->full_name}'s IPCR for revision.");
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Every key in the submitted array must be one of this IPCR's own items.
     *
     * Failing loudly rather than skipping unknown ids: a form that names a
     * line from someone else's IPCR is not a partial save, it is an attempt to
     * write marks where the approver has no standing.
     *
     * @param  array<int, int>  $ownItemIds
     */
    private function onlyOwnItems(array $ownItemIds): \Closure
    {
        $own = array_map('intval', $ownItemIds);

        return function (string $attribute, mixed $value, \Closure $fail) use ($own): void {
            $submitted = array_map('intval', array_keys((array) $value));

            if (array_diff($submitted, $own) !== []) {
                $fail('One of the lines being rated does not belong to this IPCR.');
            }
        };
    }

    private function record(Ipcr $ipcr, Request $request, ApprovalStage $stage, ApprovalAction $action, ?string $remarks = null): void
    {
        $ipcr->approvals()->create([
            'approver_employee_id' => $request->user()->employee->id,
            'stage'                => $stage,
            'action'               => $action,
            'remarks'              => $remarks,
            'acted_at'             => now(),
        ]);
    }
}
