<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The owner chooses whether the IPCR holds targets only or the actual
 * accomplishments as well.
 *
 * `with_accomplishment` is the default because that was the app's previous
 * behaviour - the accomplishment field was always available - so no existing
 * record changes meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcrs', function (Blueprint $table) {
            $table->string('mode', 30)
                ->default('with_accomplishment')
                ->after('status');   // targets_only|with_accomplishment
        });
    }

    public function down(): void
    {
        Schema::table('ipcrs', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
