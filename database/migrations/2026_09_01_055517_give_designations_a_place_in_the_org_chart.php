<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a designation sits.
 *
 * A designation had a title and nothing else, so "OIC - HRMO" was a name with
 * no place attached. An employee designated to it stayed, as far as the system
 * could tell, in the section of their plantilla position - their IPCR went to
 * a section head with no sight of the work, and the division actually running
 * them never saw their name.
 *
 * Both are nullable and both are allowed at once. A designation may name a
 * whole division and no section under it, which is the ordinary shape for an
 * officer-in-charge: they run a unit rather than sit in one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designations', function (Blueprint $table): void {
            $table->foreignId('division_id')->nullable()->after('description')
                ->constrained()->nullOnDelete();

            $table->foreignId('section_id')->nullable()->after('division_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('designations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('section_id');
            $table->dropConstrainedForeignId('division_id');
        });
    }
};
