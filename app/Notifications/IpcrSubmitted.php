<?php

namespace App\Notifications;

/** An IPCR has been handed in and is waiting for this person's assessment. */
class IpcrSubmitted extends IpcrNotification
{
    protected function message(): string
    {
        $late = $this->ipcr->isLate()
            ? ' It was handed in ' . $this->ipcr->daysLate() . ' day(s) past the deadline.'
            : '';

        return "{$this->ownerName()} submitted their IPCR for {$this->periodName()}. "
            . 'It is waiting for your assessment.' . $late;
    }
}
