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

    /** Kailangan ba ng remarks? Oo kapag ibinalik - dapat alam ng empleyado ang dahilan. */
    public function requiresRemarks(): bool
    {
        return $this === self::Returned;
    }
}
