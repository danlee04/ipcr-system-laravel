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
enum FunctionCategory: string
{
    case Strategic = 'strategic';
    case Core      = 'core';
    case Support   = 'support';

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
            self::Strategic => 'bg-violet-100 text-violet-800 ring-violet-500/20',
            self::Core      => 'bg-blue-100 text-blue-800 ring-blue-500/20',
            self::Support   => 'bg-teal-100 text-teal-800 ring-teal-500/20',
        };
    }
}
