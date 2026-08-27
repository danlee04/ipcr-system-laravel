<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Four columns that outlived whatever wanted them.
 *
 *   ipcrs.common_weight, ipcrs.common_rating
 *       Belonged to a fourth function category that no longer exists. A
 *       function open to everyone is now Core, Support or Strategic and is
 *       counted under its own category.
 *
 *   job_functions.default_weight
 *       A suggested weight, typed once per function and then ignored. An IPCR
 *       line now takes whatever its category has not spent, which is right at
 *       every point rather than only after somebody does the arithmetic.
 *
 *   employees.date_hired
 *       Asked for on a form that stopped asking.
 *
 * Checked against the live data before writing this: every one of them was
 * empty apart from three demo records seeded with the same date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcrs', function (Blueprint $table): void {
            $table->dropColumn(['common_weight', 'common_rating']);
        });

        Schema::table('job_functions', function (Blueprint $table): void {
            $table->dropColumn('default_weight');
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn('date_hired');
        });
    }

    /**
     * The shape comes back. The values do not - there were none worth keeping,
     * which is the whole reason the columns went.
     */
    public function down(): void
    {
        Schema::table('ipcrs', function (Blueprint $table): void {
            $table->decimal('common_weight', 5, 2)->default(0)->after('support_weight');
            $table->decimal('common_rating', 4, 3)->nullable()->after('support_rating');
        });

        Schema::table('job_functions', function (Blueprint $table): void {
            $table->decimal('default_weight', 5, 2)->nullable();
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->date('date_hired')->nullable();
        });
    }
};
