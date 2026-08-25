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
            self::Assessed  => 'For Final Rating',
            self::Approved  => 'Approved',
            self::Returned  => 'Returned for Revision',
        };
    }

    /** Tailwind classes for the status badge. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft     => 'bg-gray-100 text-gray-700 ring-gray-500/20',
            self::Submitted => 'bg-amber-100 text-amber-800 ring-amber-500/20',
            self::Assessed  => 'bg-blue-100 text-blue-800 ring-blue-500/20',
            self::Approved  => 'bg-emerald-100 text-emerald-800 ring-emerald-500/20',
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
