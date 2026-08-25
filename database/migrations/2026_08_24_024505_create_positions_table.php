<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantilla positions (e.g. "Statistician II", "Nurse III").
 * Ito ang pinanggagalingan ng CORE functions ng isang empleyado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('title');                                 // "Statistician II"
            $table->string('item_number', 50)->nullable()->unique(); // plantilla item no.
            $table->unsignedTinyInteger('salary_grade')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};