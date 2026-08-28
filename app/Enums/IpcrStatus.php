<?php

namespace App\Enums;

enum IpcrStatus: string
{
    case Draft     = 'draft';
    case Submitted = 'submitted';
    case Assessed  = 'assessed';
    case Approved  = 'approved';
    case Returned  = 'returned';

    /** The wording the user sees - matches the terminology used in the flow. */
    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Draft',
            self::Submitted => 'For Assessment',
            self::Assessed  => 'For Final Approval',
            self::Approved  => 'Approved',
            self::Returned  => 'Returned for Revision',
        };
    }

    /** Tailwind classes for the status badge. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft     => 'bg-ink-100 text-ink-700 ring-ink-400/40',
            self::Submitted => 'bg-amber-100 text-amber-800 ring-amber-500/20',
            self::Assessed  => 'bg-brand-100 text-brand-800 ring-brand-500/25',
            self::Approved  => 'bg-mint-100 text-mint-800 ring-mint-500/25',
            self::Returned  => 'bg-red-100 text-red-800 ring-red-500/20',
        };
    }

    /** Can the owner still edit the contents? */
    public function isEditableByOwner(): bool
    {
        return in_array($this, [self::Draft, self::Returned], true);
    }

    /** Is the whole process finished? */
    public function isFinal(): bool
    {
        return $this === self::Approved;
    }
}
