<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rating periods. Karaniwan sa CSC: dalawang semestre kada taon.
 * Kapag `closed` na ang period, hindi na pwedeng gumawa/mag-edit ng IPCR dito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // "January - June 2026"
            $table->unsignedSmallInteger('year');           // 2026
            $table->string('type', 20);                     // first_semester|second_semester|annual
            $table->date('start_date');
            $table->date('end_date');
            $table->date('submission_deadline')->nullable();
            $table->string('status', 20)->default('open');  // open|closed
            $table->timestamps();

            $table->unique(['year', 'type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_periods');
    }
};
