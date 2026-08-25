<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The IPCR header. One record per employee per period.
 *
 * Status flow:
 *   draft -> submitted -> assessed -> approved
 *              |             |
 *              +-- returned -+   (back to the employee for revision)
 *
 * How that maps to the agreed flow:
 *   submitted = "For Assessment"   (in the assessor's inbox)
 *   assessed  = "For Final Rating" (in the final approver's inbox)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcrs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ipcr_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete(); // the ratee

            // Snapshot taken at submission time. Even if the employee changes
            // position or office tomorrow, the old IPCR still prints correctly.
            $table->string('position_title')->nullable();
            $table->string('office_name')->nullable();

            // Approval chain. Resolved at submit time, but HR/Admin can override it.
            $table->foreignId('assessor_employee_id')->nullable()
                ->constrained('employees')->nullOnDelete();
            $table->foreignId('final_approver_employee_id')->nullable()
                ->constrained('employees')->nullOnDelete();

            $table->string('status', 20)->default('draft');  // draft|submitted|assessed|approved|returned

            // Weight per category (%). These must total 100.
            $table->decimal('strategic_weight', 5, 2)->default(30);
            $table->decimal('core_weight', 5, 2)->default(50);
            $table->decimal('support_weight', 5, 2)->default(20);
            $table->decimal('common_weight', 5, 2)->default(0);

            // Computed by the system from ipcr_items. Never edit by hand.
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

            // One IPCR per employee per period.
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
