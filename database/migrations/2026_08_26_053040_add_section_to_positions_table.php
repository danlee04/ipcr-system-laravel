<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A position belongs to a section, and a section to a division.
 *
 * That chain is what lets the Functions screen narrow Division -> Section ->
 * Position. The division is not stored here: it is reached through the
 * section, so moving a section between divisions cannot leave the positions
 * inside it pointing at the old one.
 *
 * Nullable, because not every post sits in a section - Chief of Hospital and
 * other office-wide posts do not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table): void {
            $table->foreignId('section_id')
                ->nullable()
                ->after('title')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('section_id');
        });
    }
};
