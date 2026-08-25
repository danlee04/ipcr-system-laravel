<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master catalog of the functions that can be picked when building an IPCR.
 *
 * Note the name is `job_functions`, NOT `functions` -- `function` is a
 * reserved word in PHP, so the model could not be called `class Function`.
 *
 * What each category attaches to:
 *   core      -> position_id     (from the plantilla position)
 *   strategic -> designation_id  (from the designation / OIC role)
 *   support   -> designation_id  (from the designation / OIC role)
 *   common    -> both null       (open pool, anyone may pick from it)
 *
 * This catalog is only a SET OF SUGGESTIONS - the employee still adds items
 * to their actual IPCR by hand. Nothing is auto-populated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_functions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('position_id')->nullable()
                ->constrained()->cascadeOnDelete();
            $table->foreignId('designation_id')->nullable()
                ->constrained()->cascadeOnDelete();

            $table->string('category', 20);                      // strategic|core|support|common
            $table->text('title');                               // the output / objective
            $table->text('success_indicator')->nullable();       // target + measure
            $table->decimal('default_weight', 5, 2)->nullable(); // suggested weight, %
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'position_id']);
            $table->index(['category', 'designation_id']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_functions');
    }
};
