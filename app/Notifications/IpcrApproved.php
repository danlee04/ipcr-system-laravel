<?php

namespace App\Notifications;

/** The last step. The rating on the IPCR is now the record. */
class IpcrApproved extends IpcrNotification
{
    protected function message(): string
    {
        $rating = $this->ipcr->final_adjectival_rating;

        return "Your IPCR for {$this->periodName()} has been approved."
            . ($rating === null ? '' : " Final rating: {$rating}.");
    }
}
