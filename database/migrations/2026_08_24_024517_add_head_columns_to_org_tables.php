<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idinadagdag dito ang head assignments dahil circular ang dependency:
 *   divisions -> employees -> sections -> divisions
 *
 * MAHALAGA: routing/approval lang ang gamit ng mga column na ito.
 * WALA itong epekto sa functions na lalabas sa sariling IPCR ng head.
 * Kung gusto mong may "Section Head duties" sa IPCR niya, gumawa ng
 * hiwalay na designation record para dun (manual, gaya ng napagkasunduan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->foreignId('division_head_employee_id')->nullable()->after('code')
                ->constrained('employees')->nullOnDelete();
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->foreignId('section_head_employee_id')->nullable()->after('code')
                ->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('division_head_employee_id');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('section_head_employee_id');
        });
    }
};
