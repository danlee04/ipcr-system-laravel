<?php

namespace App\Notifications;

use App\Models\Ipcr;
use Illuminate\Notifications\Notification;

/**
 * Something happened to an IPCR, and one person needs to know.
 *
 * Stored in the database and read inside the app. Not sent by mail: no mail
 * server is configured here, and a notification that quietly fails to send is
 * worse than one that was never promised.
 *
 * Each subclass supplies the sentence. The shape is shared so the list can
 * render any of them without asking which it is holding.
 */
abstract class IpcrNotification extends Notification
{
    public function __construct(public readonly Ipcr $ipcr) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'ipcr_id' => $this->ipcr->id,
            'period'  => $this->ipcr->period?->name,
            'message' => $this->message(),
        ];
    }

    abstract protected function message(): string;

    protected function ownerName(): string
    {
        return $this->ipcr->employee?->full_name ?? 'An employee';
    }

    protected function periodName(): string
    {
        return $this->ipcr->period?->name ?? 'the current period';
    }
}
