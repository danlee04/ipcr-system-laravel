<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Designations / OIC roles (e.g. "OIC - Budget Officer", "OIC - HRMO").
 * Ito ang pinanggagalingan ng SUPPORT at STRATEGIC functions.
 * Isang empleyado ay pwedeng may maraming ACTIVE designations nang sabay-sabay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('title');                 // "OIC - Budget Officer"
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designations');
    }
};
