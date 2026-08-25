<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sections (nasa ilalim ng isang Division).
 * Ang section_head_employee_id ay idadagdag sa hiwalay na migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();                        // bawal burahin ang division na may sections
            $table->string('name');                            // "Statistics Unit"
            $table->string('code', 20)->nullable()->unique();  // "STAT"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
