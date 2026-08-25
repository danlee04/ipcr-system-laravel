<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ang IPCR header. Isang record kada empleyado kada period.
 *
 * Status flow:
 *   draft -> submitted -> assessed -> approved
 *              |             |
 *              +-- returned -+   (pabalik sa empleyado para i-revise)
 *
 * Mapping sa flow na hiningi mo:
 *   submitted = "For Assessment"   (nasa inbox ng assessor)
 *   assessed  = "For Final Rating" (nasa inbox ng final approver)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcrs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ipcr_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete(); // ang ratee

            // Snapshot sa panahon ng pag-submit. Kahit mapalitan ang position/office
            // ng empleyado bukas, mananatiling tama ang printout ng lumang IPCR.
            $table->string('position_title')->nullable();
            $table->string('office_name')->nullable();

            // Approval chain. Naka-resolve sa submit time, pero pwedeng i-override ng HR/Admin.
            $table->foreignId('assessor_employee_id')->nullable()
                ->constrained('employees')->nullOnDelete();
            $table->foreignId('final_approver_employee_id')->nullable()
                ->constrained('employees')->nullOnDelete();

            $table->string('status', 20)->default('draft');  // draft|submitted|assessed|approved|returned

            // Timbang kada kategorya (%). Dapat 100 ang kabuuan.
            $table->decimal('strategic_weight', 5, 2)->default(30);
            $table->decimal('core_weight', 5, 2)->default(50);
            $table->decimal('support_weight', 5, 2)->default(20);
            $table->decimal('common_weight', 5, 2)->default(0);

            // Kinukwenta ng system mula sa ipcr_items. Huwag i-edit nang manual.
            $table->decimal('strategic_rating', 4, 3)->nullable();
            $table->decimal('core_rating', 4, 3)->nullable();
            $table->decimal('support_rating', 4, 3)->nullable();
            $table->decimal('common_rating', 4, 3)->nullable();
            $table->decimal('final_numerical_rating', 4, 3)->nullable();
            $table->string('final_adjectival_rating', 30)->nullable(); // Outstanding, Very Satisfactory, ...

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('assessed_at')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            // Isang IPCR lang kada empleyado kada period.
            $table->unique(['employee_id', 'ipcr_period_id']);
            $table->index(['status', 'assessor_employee_id']);
            $table->index(['status', 'final_approver_employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcrs');
    }
};
