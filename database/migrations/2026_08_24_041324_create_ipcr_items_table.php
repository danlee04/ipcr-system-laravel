<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The IPCR line items - one per row on the official CSC form.
 *
 * The employee adds these by hand. `job_function_id` is an optional link
 * back to the catalog so you can tell where an item came from, but it may be
 * left blank when the employee typed the entry themselves.
 *
 * Rating scale per dimension (1-5):
 *   5 Outstanding | 4 Very Satisfactory | 3 Satisfactory | 2 Unsatisfactory | 1 Poor
 * They are nullable because some outputs have a dimension that does not
 * apply (no Timeliness, say) - those must not be counted in the average.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ipcr_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_function_id')->nullable()
                ->constrained()->nullOnDelete();

            $table->string('category', 20);                  // strategic|core|support|common
            $table->text('output');                          // Output / MFO / PAP
            $table->text('success_indicator')->nullable();   // Target + Measure
            $table->decimal('weight', 5, 2)->nullable();     // weight within the category, %

            $table->text('actual_accomplishment')->nullable();

            $table->decimal('quality_rating', 3, 2)->nullable();     // Q
            $table->decimal('efficiency_rating', 3, 2)->nullable();  // E
            $table->decimal('timeliness_rating', 3, 2)->nullable();  // T
            $table->decimal('average_rating', 4, 3)->nullable();     // A - computed

            $table->text('remarks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['ipcr_id', 'category', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_items');
    }
};
