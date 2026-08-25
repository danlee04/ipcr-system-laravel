<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Head assignments are added here because the dependency is circular:
 *   divisions -> employees -> sections -> divisions
 *
 * IMPORTANT: these columns are used for routing and approval only. They have
 * no effect on the functions that appear in the head's own IPCR. If you want
 * "Section Head duties" on their IPCR, create a separate designation record
 * for it - manually, as agreed.
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
