<?php

namespace App\Notifications;

/** The assessment is finished and the IPCR now waits for the final approval. */
class IpcrAssessed extends IpcrNotification
{
    protected function message(): string
    {
        $assessor = $this->ipcr->assessor?->full_name ?? 'The assessor';

        return "{$assessor} finished assessing {$this->ownerName()}'s IPCR for {$this->periodName()}. "
            . 'It is waiting for your final approval.';
    }
}
