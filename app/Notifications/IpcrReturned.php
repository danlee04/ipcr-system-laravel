<?php

namespace App\Notifications;

use App\Models\Ipcr;

/**
 * An IPCR has been sent back to its owner.
 *
 * The reason travels with it. Being told your work was returned without being
 * told why is the one thing worse than not being told at all.
 */
class IpcrReturned extends IpcrNotification
{
    public function __construct(Ipcr $ipcr, private readonly ?string $remarks = null)
    {
        parent::__construct($ipcr);
    }

    protected function message(): string
    {
        $reason = trim((string) $this->remarks);

        return "Your IPCR for {$this->periodName()} was returned for revision."
            . ($reason === '' ? '' : " Reason: {$reason}");
    }
}
