<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the audit trail record an action taken by somebody outside the chain.
 *
 * Every row used to belong to an employee, because every action belonged to an
 * approver. Administrative actions do not: the seeded administrator has no
 * employee record at all, and requiring one would make the audit row
 * impossible to write for exactly the actions that most need auditing.
 *
 * So approver_employee_id becomes optional, and acted_by_user_id carries the
 * account that acted. An approver's row still fills in both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcr_approvals', function (Blueprint $table) {
            $table->foreignId('acted_by_user_id')->nullable()->after('approver_employee_id')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('ipcr_approvals', function (Blueprint $table) {
            $table->unsignedBigInteger('approver_employee_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_approvals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acted_by_user_id');
        });
    }
};
