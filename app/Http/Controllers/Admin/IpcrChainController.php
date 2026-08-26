<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalStage;
use App\Enums\IpcrStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReopenIpcrRequest;
use App\Http\Requests\Admin\RerouteIpcrRequest;
use App\Models\Employee;
use App\Models\Ipcr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The two things the ordinary flow cannot do, kept together because they are
 * the same kind of act: someone outside an IPCR's approval chain reaching into
 * it, on the record.
 *
 *   Set the chain - IpcrRoutingService refuses to route the Chief of
 *                   Hospital, who has nobody above them. Without this their
 *                   own IPCR can never be submitted. It also covers a head on
 *                   leave, or an org chart that has not caught up.
 *
 *   Reopen        - Approved is otherwise a one-way door. A mark keyed in
 *                   wrong and noticed after signing would only be fixable in
 *                   the database.
 *
 * Every action here writes an ipcr_approvals row. An override that leaves no
 * trace is worse than no override: it makes the history a lie rather than
 * merely incomplete.
 */
class IpcrChainController extends Controller
{
    /** Name who assesses and who gives the final approval. */
    public function update(RerouteIpcrRequest $request, Ipcr $ipcr): RedirectResponse
    {
        $this->authorize('reroute', $ipcr);

        $validated = $request->validated();

        $assessor = Employee::findOrFail($validated['assessor_employee_id']);
        $finalApprover = Employee::findOrFail($validated['final_approver_employee_id']);

        // Read before the update: the point of the record is what changed.
        $was = [
            'assessor'      => $ipcr->assessor?->full_name,
            'finalApprover' => $ipcr->finalApprover?->full_name,
        ];

        DB::transaction(function () use ($ipcr, $assessor, $finalApprover, $was, $validated, $request): void {
            $ipcr->update([
                'assessor_employee_id'       => $assessor->id,
                'final_approver_employee_id' => $finalApprover->id,
                'chain_overridden_at'        => now(),
            ]);

            $this->record($ipcr, $request, ApprovalAction::Rerouted, implode(' ', [
                $this->changeSentence('For Assessment', $was['assessor'], $assessor->full_name),
                $this->changeSentence('For Final Approval', $was['finalApprover'], $finalApprover->full_name),
                $validated['reason'],
            ]));
        });

        return redirect()->route('admin.ipcrs.index')->with(
            'status',
            "Approval chain set for {$ipcr->employee?->full_name}: {$assessor->full_name} assesses, {$finalApprover->full_name} gives the final approval."
        );
    }

    /**
     * Hand the chain back to the org chart.
     *
     * Clears the stamp as well as the flag, so the next submission resolves
     * from scratch and picks up whoever holds the post today.
     */
    public function destroy(Request $request, Ipcr $ipcr): RedirectResponse
    {
        $this->authorize('releaseChain', $ipcr);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'reason.required' => 'Say why the chain is going back to automatic. It goes on the record.',
        ]);

        DB::transaction(function () use ($ipcr, $validated, $request): void {
            $ipcr->update([
                'assessor_employee_id'       => null,
                'final_approver_employee_id' => null,
                'chain_overridden_at'        => null,
            ]);

            $this->record($ipcr, $request, ApprovalAction::Rerouted, implode(' ', [
                'Handed back to automatic routing from the org chart.',
                $validated['reason'],
            ]));
        });

        return redirect()->route('admin.ipcrs.index')->with(
            'status',
            "{$ipcr->employee?->full_name}'s IPCR now routes itself from the org chart."
        );
    }

    /** Undo an approval and send the IPCR back a step. */
    public function reopen(ReopenIpcrRequest $request, Ipcr $ipcr): RedirectResponse
    {
        $this->authorize('reopen', $ipcr);

        $validated = $request->validated();
        $toAssessor = $validated['target'] === ReopenIpcrRequest::TARGET_ASSESSMENT;

        // Kept in the remark before it is wiped from the columns. Clearing it
        // without recording it would destroy the only copy of a rating that
        // somebody has already signed for.
        $previous = $this->previousRatingSentence($ipcr);

        DB::transaction(function () use ($ipcr, $toAssessor, $previous, $validated, $request): void {
            $ipcr->update($this->clearedRatings() + [
                'status'       => $toAssessor ? IpcrStatus::Submitted : IpcrStatus::Returned,
                'assessed_at'  => null,
                'approved_at'  => null,
            ]);

            $this->record($ipcr, $request, ApprovalAction::Reopened, implode(' ', array_filter([
                $toAssessor
                    ? 'Reopened for re-assessment.'
                    : 'Reopened and returned to the employee for revision.',
                $previous,
                $validated['reason'],
            ])));
        });

        $destination = $toAssessor
            ? ($ipcr->assessor?->nameWithPost() ?? 'whoever assesses it')
            : ($ipcr->employee?->full_name ?? 'the employee');

        return redirect()->route('admin.ipcrs.index')
            ->with('status', "Reopened {$ipcr->employee?->full_name}'s IPCR. It is now with {$destination}.");
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * The ratings an approved IPCR carried, as a sentence for the audit trail.
     *
     * Empty when there was nothing to lose, so the remark does not carry a
     * hollow "Previous final rating: none".
     */
    private function previousRatingSentence(Ipcr $ipcr): string
    {
        if ($ipcr->final_numerical_rating === null) {
            return '';
        }

        $numeric = number_format((float) $ipcr->final_numerical_rating, 3);
        $adjectival = $ipcr->final_adjectival_rating;

        return "Previous final rating: {$numeric}" . ($adjectival ? " ({$adjectival})" : '') . '.';
    }

    /**
     * A reopened IPCR must not keep showing what it was approved with. The
     * number is recomputed when it is approved again; until then it is not a
     * fact about this IPCR, and a stale one on screen is how a wrong rating
     * gets quoted in a memo.
     *
     * The per-line Q/E/T marks are deliberately left alone - an assessor
     * correcting one line should not have to redo twenty.
     */
    private function clearedRatings(): array
    {
        return [
            'strategic_rating'        => null,
            'core_rating'             => null,
            'support_rating'          => null,
            'final_numerical_rating'  => null,
            'final_adjectival_rating' => null,
        ];
    }

    private function changeSentence(string $slot, ?string $from, string $to): string
    {
        return $from === null
            ? "{$slot} set to {$to}."
            : ($from === $to ? "{$slot} unchanged ({$to})." : "{$slot} changed from {$from} to {$to}.");
    }

    /**
     * The audit row.
     *
     * approver_employee_id stays null: this action was not taken by anyone in
     * the chain, and pinning it on an employee would misattribute it. The user
     * account carries the attribution instead - HR and administrators need not
     * be employees, and the seeded administrator is not one.
     */
    private function record(Ipcr $ipcr, Request $request, ApprovalAction $action, string $remarks): void
    {
        $ipcr->approvals()->create([
            'approver_employee_id' => null,
            'acted_by_user_id'     => $request->user()->id,
            'stage'                => ApprovalStage::Administrative,
            'action'               => $action,
            'remarks'              => $remarks,
            'acted_at'             => now(),
        ]);
    }
}
