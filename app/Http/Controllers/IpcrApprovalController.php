<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalStage;
use App\Enums\IpcrStatus;
use App\Models\Ipcr;
use App\Notifications\IpcrApproved;
use App\Notifications\IpcrAssessed;
use App\Notifications\IpcrReturned;
use App\Services\RatingCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The approval side of an IPCR: Submitted -> Assessed -> Approved.
 *
 * Nobody here rates anything. The employee marks their own IPCR before
 * submitting it - they did the work and they hold the evidence - and these two
 * stages agree with it or send it back. There is no form on this side because
 * there is nothing on this side to type.
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

    /** Finish the assessment and pass the IPCR to the final approver. */
    public function assess(Request $request, Ipcr $ipcr): RedirectResponse
    {
        $this->authorize('assess', $ipcr);

        // Targets only is a commitment made before the work: there is nothing
        // to rate, and no rating to withhold the approval for.
        $rated = $ipcr->showsAccomplishment();
        $breakdown = $this->calculator->for($ipcr->load('items'));

        if ($rated && ! $breakdown->complete) {
            return back()->with('error', $ipcr->items()->doesntExist()
                ? 'This IPCR has no functions to rate.'
                : "{$breakdown->unratedItemCount} function(s) carry no mark. Return the IPCR so the employee can finish rating it - the marks are theirs to give.");
        }

        DB::transaction(function () use ($ipcr, $breakdown, $rated, $request): void {
            $ipcr->update(($rated ? $breakdown->toIpcrColumns() : []) + [
                'status'      => IpcrStatus::Assessed,
                'assessed_at' => now(),
            ]);

            $this->record($ipcr, $request, ApprovalStage::Assessment, ApprovalAction::Approved);
        });

        // After the transaction, not inside it: a notification for a change
        // that then rolls back is a lie nobody can take back.
        $ipcr->finalApprover?->user?->notify(new IpcrAssessed($ipcr->fresh()));

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
        $rated = $ipcr->showsAccomplishment();
        $breakdown = $this->calculator->for($ipcr->load('items'));

        if ($rated && ! $breakdown->complete) {
            return back()->with('error', 'This IPCR is not fully rated. Return it so the employee can finish.');
        }

        DB::transaction(function () use ($ipcr, $breakdown, $rated, $request): void {
            $ipcr->update(($rated ? $breakdown->toIpcrColumns() : []) + [
                'status'      => IpcrStatus::Approved,
                'approved_at' => now(),
            ]);

            $this->record($ipcr, $request, ApprovalStage::FinalRating, ApprovalAction::Approved);
        });

        $ipcr->employee?->user?->notify(new IpcrApproved($ipcr->fresh()));

        return redirect()->route('approvals.inbox')->with(
            'status',
            $rated
                ? "Approved {$ipcr->employee->full_name}'s IPCR — {$breakdown->finalNumeric} ({$breakdown->finalAdjectival->value})."
                : "Approved {$ipcr->employee->full_name}'s targets for this period."
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

        // The reason travels with it. Being told your work came back without
        // being told why is the one thing worse than not being told.
        $ipcr->employee?->user?->notify(new IpcrReturned($ipcr->fresh(), $validated['remarks']));

        return redirect()->route('approvals.inbox')
            ->with('status', "Returned {$ipcr->employee->full_name}'s IPCR for revision.");
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

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
