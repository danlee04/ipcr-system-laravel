<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Common" was never a kind of work - it said who a function reached.
 *
 * A function open to everyone is now a Core, Support or Strategic function
 * that names neither a position nor a designation, so the extra category and
 * the "counts towards" column it needed both go.
 *
 * Any row still marked common takes the category it said it counted towards.
 * One that never said falls back to Support, the lightest of the three: a
 * function nobody filed is unlikely to be somebody's core work, and guessing
 * high would inflate a rating.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('job_functions')
            ->where('category', 'common')
            ->whereNotNull('rating_category')
            ->update(['category' => DB::raw('rating_category')]);

        DB::table('job_functions')
            ->where('category', 'common')
            ->update(['category' => 'support']);

        // Lines already on an IPCR carry their own category, and one built
        // from the common pool was filed under a rated category when it was
        // added. Any that predate that rule are given the same fallback.
        DB::table('ipcr_items')->where('category', 'common')->update(['category' => 'support']);

        Schema::table('job_functions', function (Blueprint $table) {
            $table->dropColumn('rating_category');
        });
    }

    public function down(): void
    {
        Schema::table('job_functions', function (Blueprint $table) {
            $table->string('rating_category', 20)->nullable()->after('category');
        });
    }
};
