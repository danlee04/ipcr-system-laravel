<?php

namespace App\Enums;

enum ApprovalAction: string
{
    case Approved = 'approved';
    case Returned = 'returned';

    /** HR or an administrator set who assesses and who gives the final rating. */
    case Rerouted = 'rerouted';

    /** HR or an administrator undid an approval. */
    case Reopened = 'reopened';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Approved',
            self::Returned => 'Returned for Revision',
            self::Rerouted => 'Approval Chain Changed',
            self::Reopened => 'Reopened',
        };
    }

    /**
     * Are remarks required?
     *
     * Yes for everything except a plain approval. A return has to tell the
     * employee what to fix; an administrative action has to say why somebody
     * outside the chain touched the IPCR at all.
     */
    public function requiresRemarks(): bool
    {
        return $this !== self::Approved;
    }
}
