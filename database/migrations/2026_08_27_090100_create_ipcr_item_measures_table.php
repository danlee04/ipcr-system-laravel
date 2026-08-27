<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the employee actually reported, per measure, on one IPCR line.
 *
 * Kept beside the mark rather than instead of it. ipcr_items still carries
 * quality_rating, efficiency_rating and timeliness_rating - those are what the
 * rating is built from, and what a line typed by hand has - but for a function
 * with a rubric they are now worked out from these figures rather than chosen.
 *
 * The count and total are kept as well as the value they make, because the
 * accomplishment sentence says "12 of 12" and that cannot be recovered from
 * the percentage alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_item_measures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_item_id')->constrained()->cascadeOnDelete();

            $table->string('measure', 20);   // quality|efficiency|timeliness

            // The figure the bands are read against. For a count that is the
            // percentage the two numbers make.
            $table->decimal('value', 12, 2)->nullable();

            $table->decimal('reported_count', 12, 2)->nullable();
            $table->decimal('reported_total', 12, 2)->nullable();

            $table->timestamps();

            $table->unique(['ipcr_item_id', 'measure']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_item_measures');
    }
};
