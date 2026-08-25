<?php

namespace App\Policies;

use App\Models\Ipcr;
use App\Models\User;

/**
 * Sino ang pwedeng tumingin at mag-edit ng isang IPCR.
 *
 * Tandaan ang pagkakaiba:
 *   view   - may-ari, assessor, at final approver. Kailangan nilang
 *            makita ang IPCR pagkatapos i-submit para maaksyunan.
 *   update - MAY-ARI LANG. Ang pagpasok ng Q/E/T ratings ng assessor
 *            ay hiwalay na daan (sariling controller at policy method),
 *            hindi sa pamamagitan ng update na ito.
 */
class IpcrPolicy
{
    /** Ang IPCR ba ay pag-aari ng user na ito? */
    private function owns(User $user, Ipcr $ipcr): bool
    {
        return $ipcr->employee?->user_id === $user->id;
    }

    /** Isa ba siya sa dalawang approver na naka-stamp sa IPCR? */
    private function isApprover(User $user, Ipcr $ipcr): bool
    {
        $employeeId = $user->employee?->id;

        if ($employeeId === null) {
            return false;
        }

        return $employeeId === $ipcr->assessor_employee_id
            || $employeeId === $ipcr->final_approver_employee_id;
    }

    public function view(User $user, Ipcr $ipcr): bool
    {
        return $this->owns($user, $ipcr) || $this->isApprover($user, $ipcr);
    }

    public function update(User $user, Ipcr $ipcr): bool
    {
        return $this->owns($user, $ipcr);
    }
}
