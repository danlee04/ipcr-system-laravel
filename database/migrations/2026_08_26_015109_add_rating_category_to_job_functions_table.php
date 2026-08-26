<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Common" says who may pick a function, not how it is rated.
 *
 * A common function is open to everyone, but the rating only knows strategic,
 * core and support. This column records which of those a common function
 * counts towards. It stays null for the other categories, where the function's
 * own category already answers the question.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_functions', function (Blueprint $table): void {
            $table->string('rating_category', 20)->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('job_functions', function (Blueprint $table): void {
            $table->dropColumn('rating_category');
        });
    }
};
