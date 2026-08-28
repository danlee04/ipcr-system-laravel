<?php

namespace App\Enums;

/**
 * The kind of work a function is. Three, and only three - the same three the
 * rating knows how to weigh.
 *
 * There used to be a fourth, "Common", which was not a kind of work at all: it
 * said the function reached everybody. Sitting in this box, it forced a second
 * question - which of the three does it count towards - that had to be
 * answered before anything could be rated, and could be forgotten.
 *
 * Who a function reaches is asked separately now, on the function itself: a
 * position, a designation, or everyone.
 */
/*
 * Declared in reading order - Core, Support, Strategic - because cases() is
 * what fills every dropdown and every list, and that is the order the sheet is
 * read in everywhere else.
 */
enum FunctionCategory: string
{
    case Core      = 'core';
    case Support   = 'support';
    case Strategic = 'strategic';

    /** The wording the user sees on the IPCR form. */
    public function label(): string
    {
        return match ($this) {
            self::Strategic => 'Strategic Function',
            self::Core      => 'Core Function',
            self::Support   => 'Support Function',
        };
    }

    /** Tailwind classes for the category badge/tab. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Strategic => 'bg-nav-900 text-nav-100 ring-nav-700',
            self::Core      => 'bg-brand-100 text-brand-800 ring-brand-500/25',
            self::Support   => 'bg-mint-100 text-mint-800 ring-mint-500/25',
        };
    }
}
