<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ang mga linya ng IPCR - katumbas ng bawat row sa official CSC form.
 *
 * Manu-mano ang pag-add nito ng empleyado. Ang `job_function_id` ay
 * opsyonal na link pabalik sa catalog (para malaman kung saan galing),
 * pero pwede ring blangko kung sariling type ng empleyado ang laman.
 *
 * Rating scale kada dimension (1-5):
 *   5 Outstanding | 4 Very Satisfactory | 3 Satisfactory | 2 Unsatisfactory | 1 Poor
 * Nullable sila dahil may output na hindi applicable ang isang dimension
 * (hal. walang Timeliness) - hindi 'yun dapat isama sa average.
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
            $table->decimal('weight', 5, 2)->nullable();     // timbang sa loob ng kategorya, %

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
