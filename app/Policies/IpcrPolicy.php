<?php

namespace App\Policies;

use App\Enums\IpcrStatus;
use App\Models\Ipcr;
use App\Models\User;

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
     * Drafts only, and owners only.
     *
     * isEditableByOwner() is deliberately not used here: it also covers
     * Returned, and a returned IPCR already has an approval row recorded.
     * ipcrs has no soft delete and ipcr_approvals cascades on delete, so
     * deleting would permanently destroy that audit trail. Only a draft is
     * safe - it has never been passed to anyone.
     */
    public function delete(User $user, Ipcr $ipcr): bool
    {
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
