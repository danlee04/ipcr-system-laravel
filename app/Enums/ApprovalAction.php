<?php

namespace App\Enums;

enum ApprovalAction: string
{
    case Approved = 'approved';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Approved',
            self::Returned => 'Returned for Revision',
        };
    }

    /** Are remarks required? Yes when returned - the employee must know why. */
    public function requiresRemarks(): bool
    {
        return $this === self::Returned;
    }
}
