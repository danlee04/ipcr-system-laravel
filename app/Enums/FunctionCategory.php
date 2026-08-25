<?php

namespace App\Enums;

/**
 * The category of each job function / IPCR item.
 *
 * Where each one comes from (see FunctionCatalogService):
 *   Core      -> the employee's SINGLE plantilla position
 *   Strategic -> their CURRENTLY ACTIVE designations
 *   Support   -> their CURRENTLY ACTIVE designations
 *   Common    -> an open pool, available to everyone
 */
enum FunctionCategory: string
{
    case Strategic = 'strategic';
    case Core      = 'core';
    case Support   = 'support';
    case Common    = 'common';

    /** The wording the user sees on the IPCR form. */
    public function label(): string
    {
        return match ($this) {
            self::Strategic => 'Strategic Priority',
            self::Core      => 'Core Function',
            self::Support   => 'Support Function',
            self::Common    => 'Common Function',
        };
    }

    /** Tailwind classes for the category badge/tab. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Strategic => 'bg-violet-100 text-violet-800 ring-violet-500/20',
            self::Core      => 'bg-blue-100 text-blue-800 ring-blue-500/20',
            self::Support   => 'bg-teal-100 text-teal-800 ring-teal-500/20',
            self::Common    => 'bg-gray-100 text-gray-700 ring-gray-500/20',
        };
    }
}
