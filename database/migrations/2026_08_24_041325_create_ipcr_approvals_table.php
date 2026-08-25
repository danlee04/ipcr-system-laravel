<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail. Append-only: every action in the approval chain is a new row,
 * however many times the IPCR is returned and resubmitted.
 *
 * It records who acted, at which stage, when, and why - especially for
 * `returned`, so the employee knows what to fix.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ipcr_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_employee_id')->constrained('employees')->restrictOnDelete();

            $table->string('stage', 20);      // assessment|final_rating
            $table->string('action', 20);     // approved|returned
            $table->text('remarks')->nullable();
            $table->timestamp('acted_at');

            $table->timestamps();

            $table->index(['ipcr_id', 'stage']);
            $table->index('approver_employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_approvals');
    }
};
