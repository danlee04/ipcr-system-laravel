<?php

namespace App\Support;

use App\Enums\IpcrStatus;
use App\Models\Employee;
use App\Models\Ipcr;

/**
 * One name on the period sheet.
 *
 * The IPCR is nullable on purpose. An employee who never started one is not
 * missing from the roll - they are the reason the roll exists, and a report
 * built from IPCRs alone would leave them out precisely when HR is looking
 * for them.
 */
final class SummaryRow
{
    public function __construct(
        public readonly Employee $employee,
        public readonly ?Ipcr $ipcr,
    ) {}

    public function status(): ?IpcrStatus
    {
        return $this->ipcr?->status;
    }

    public function statusLabel(): string
    {
        return $this->status()?->label() ?? 'Not started';
    }

    /** Has it left the employee's hands? A draft has not. */
    public function isSubmitted(): bool
    {
        return $this->ipcr !== null && ! $this->ipcr->status->isEditableByOwner();
    }

    public function isApproved(): bool
    {
        return $this->ipcr?->status->isFinal() ?? false;
    }

    /**
     * The final rating, but only once it is final.
     *
     * A rating exists from the assessment onwards; it is not the record until
     * the Division Head has approved it, and reporting one that can still
     * change is how a figure ends up quoted before it is true.
     */
    public function approvedRating(): ?float
    {
        return $this->isApproved() && $this->ipcr->final_numerical_rating !== null
            ? (float) $this->ipcr->final_numerical_rating
            : null;
    }

    /** Handed in after the period's deadline. */
    public function isLate(): bool
    {
        return $this->ipcr?->isLate() ?? false;
    }

    public function daysLate(): int
    {
        return $this->ipcr?->daysLate() ?? 0;
    }

    public function divisionName(): string
    {
        return $this->employee->division?->name ?? 'No division';
    }

    public function sectionName(): string
    {
        return $this->employee->section?->name ?? 'No section';
    }
}
