<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * The three roles the system recognises.
 *
 * Only `admin` grants anything in Phase 1. `hr` and `employee` exist so that a
 * later split of admin duties needs no migration - just policy changes.
 *
 * Idempotent: safe to run repeatedly.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'hr', 'employee'] as $name) {
            Role::findOrCreate($name, 'web');
        }
    }
}
