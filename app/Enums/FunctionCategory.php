<?php

namespace App\Enums;

/**
 * Kategorya ng bawat job function / IPCR item.
 *
 * Saan galing ang bawat isa (tingnan ang FunctionCatalogService):
 *   Core      -> ang IISANG plantilla position ng empleyado
 *   Strategic -> mga KASALUKUYANG ACTIVE designations niya
 *   Support   -> mga KASALUKUYANG ACTIVE designations niya
 *   Common    -> open pool, bukas sa lahat
 */
enum FunctionCategory: string
{
    case Strategic = 'strategic';
    case Core      = 'core';
    case Support   = 'support';
    case Common    = 'common';

    /** Ang salitang nakikita ng user sa IPCR form. */
    public function label(): string
    {
        return match ($this) {
            self::Strategic => 'Strategic Priority',
            self::Core      => 'Core Function',
            self::Support   => 'Support Function',
            self::Common    => 'Common Function',
        };
    }

    /** Tailwind classes para sa category badge/tab. */
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
