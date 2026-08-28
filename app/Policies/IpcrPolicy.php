<?php

namespace App\Policies;

use App\Enums\IpcrStatus;
use App\Models\Ipcr;
use App\Models\User;
use App\Services\IpcrRoutingService;

/**
 * Who may view and edit an IPCR.
 *
 * Note the difference:
 *   view   - owner, assessor, and final approver. They all need to see the
 *            IPCR after submission in order to act on it.
 *   update - OWNER ONLY. Entering Q/E/T ratings is a separate path
 *            (its own controller and policy method), not something that
 *            goes through this update ability.
 */
class IpcrPolicy
{
    public function __construct(private readonly IpcrRoutingService $routing) {}

    /** Does this user own the IPCR? */
    private function owns(User $user, Ipcr $ipcr): bool
    {
        return $ipcr->employee?->user_id === $user->id;
    }

    /** Are they one of the two approvers stamped on the IPCR? */
    private function isApprover(User $user, Ipcr $ipcr): bool
    {
        $employeeId = $user->employee?->id;

        if ($employeeId === null) {
            return false;
        }

        return $employeeId === $ipcr->assessor_employee_id
            || $employeeId === $ipcr->final_approver_employee_id;
    }

    /**
     * Owner, the two approvers, and HR or an administrator.
     *
     * The admin roles are read-only here on purpose: they appear in `view` and
     * nowhere else in this policy. HR chases people who have not submitted, so
     * they have to be able to open the IPCR they are chasing - but editing,
     * assessing, approving and deleting stay with the people in the chain.
     */
    public function view(User $user, Ipcr $ipcr): bool
    {
        return $this->owns($user, $ipcr)
            || $this->isApprover($user, $ipcr)
            || $user->hasAnyRole(['admin', 'hr']);
    }

    public function update(User $user, Ipcr $ipcr): bool
    {
        return $this->owns($user, $ipcr);
    }

    /**
     * The owner scraps a draft; HR and an administrator scrap anything that is
     * still moving.
     *
     * For the owner it is drafts only. isEditableByOwner() is deliberately not
     * used: it also covers Returned, and a returned IPCR already carries an
     * approval row. ipcrs has no soft delete and ipcr_approvals cascades, so
     * that would destroy an audit trail the owner never wrote. A draft has
     * never been passed to anyone.
     *
     * HR and administrators need the wider power to set the system up and keep
     * it tidy - a half-built record that reached a Section Head is otherwise
     * stuck there for good, past the owner's reach and nobody else's business.
     *
     * Approved is the exception for everyone. It is the signed record, and it
     * is reopened first: a deliberate step, written into the IPCR's own
     * history, rather than one click away from a finished appraisal.
     */
    public function delete(User $user, Ipcr $ipcr): bool
    {
        if ($user->hasAnyRole(['admin', 'hr'])) {
            return $ipcr->status !== IpcrStatus::Approved;
        }

        return $this->owns($user, $ipcr)
            && $ipcr->status === IpcrStatus::Draft;
    }

    /**
     * Enter the Q/E/T marks and complete the assessment.
     *
     * The assessor only, and only while the IPCR is waiting on them. Once it
     * has moved to Assessed the marks are the final approver's to act on, not
     * the assessor's to keep changing.
     */
    public function assess(User $user, Ipcr $ipcr): bool
    {
        return $this->isStampedAs($user, $ipcr->assessor_employee_id)
            && $ipcr->status === IpcrStatus::Submitted;
    }

    /** Give the final rating. The final approver only, once assessment is done. */
    public function finalize(User $user, Ipcr $ipcr): bool
    {
        return $this->isStampedAs($user, $ipcr->final_approver_employee_id)
            && $ipcr->status === IpcrStatus::Assessed;
    }

    /**
     * Is this user the employee stamped in that slot on the IPCR?
     *
     * Both sides are checked for null before comparing. A user with no
     * employee record and an IPCR with no approver assigned would otherwise
     * match on `null === null`, handing the approval to anyone without an
     * employee record - the seeded administrator included.
     */
    private function isStampedAs(User $user, ?int $approverEmployeeId): bool
    {
        $employeeId = $user->employee?->id;

        return $employeeId !== null
            && $approverEmployeeId !== null
            && $employeeId === $approverEmployeeId;
    }

    /**
     * Set who assesses and who gives the final approval.
     *
     * Deliberately narrow. Routing is automatic - the Section Head assesses,
     * the Division Head gives the final approval - and nobody chooses an
     * approver where the org chart already answers the question. Two reasons:
     * a hand-set chain stops following a change of head, and a door left open
     * becomes the habit rather than the exception.
     *
     * So this needs all of:
     *   - HR or an administrator
     *   - not yet approved; once signed the chain is history, and correcting
     *     an approved IPCR goes through reopen(), which leaves a record
     *   - either the org chart cannot route this employee (the Chief of
     *     Hospital, or a section with no head), or a chain was already set by
     *     hand and now needs correcting
     */
    public function reroute(User $user, Ipcr $ipcr): bool
    {
        if (! $user->hasAnyRole(['admin', 'hr']) || $ipcr->isFinal()) {
            return false;
        }

        return $ipcr->hasOverriddenChain() || ! $this->routing->canResolveFor($ipcr->employee);
    }

    /**
     * Hand a hand-set chain back to the org chart.
     *
     * The counterpart to reroute(), and not optional: without it an IPCR
     * routed by hand while a section had no head would go on ignoring the
     * head appointed afterwards, forever.
     */
    public function releaseChain(User $user, Ipcr $ipcr): bool
    {
        return $user->hasAnyRole(['admin', 'hr'])
            && ! $ipcr->isFinal()
            && $ipcr->hasOverriddenChain();
    }

    /**
     * Undo an approval.
     *
     * The mirror of reroute(): approved IPCRs only. Anything earlier is still
     * moving under its own power and belongs to the people in its chain.
     */
    public function reopen(User $user, Ipcr $ipcr): bool
    {
        return $user->hasAnyRole(['admin', 'hr']) && $ipcr->isFinal();
    }

    /**
     * Send it back to the owner for revision.
     *
     * Whoever the IPCR is currently sitting with may return it, which is why
     * this is not simply assess() or finalize().
     */
    public function returnForRevision(User $user, Ipcr $ipcr): bool
    {
        return $this->assess($user, $ipcr) || $this->finalize($user, $ipcr);
    }
}
