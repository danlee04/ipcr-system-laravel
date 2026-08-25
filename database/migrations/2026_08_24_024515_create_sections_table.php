<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sections - each one sits under a Division.
 * section_head_employee_id is added in a separate migration.
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
                ->restrictOnDelete();                        // a division with sections cannot be deleted
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
