<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks an approval chain that HR or an administrator set by hand.
 *
 * Submission normally resolves the chain from the org chart every time, which
 * is what keeps it correct when a section head changes. A stamped chain alone
 * cannot say "leave this one alone" - the columns are filled in on every
 * submission - so the fact that a human chose it needs its own record.
 *
 * A timestamp rather than a flag: when it was overridden is the first thing
 * anyone asks when the chain looks wrong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcrs', function (Blueprint $table) {
            $table->timestamp('chain_overridden_at')->nullable()->after('final_approver_employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('ipcrs', function (Blueprint $table) {
            $table->dropColumn('chain_overridden_at');
        });
    }
};
