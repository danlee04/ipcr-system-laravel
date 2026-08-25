<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Divisions (pinakamataas na org unit sa ilalim ng Chief of Hospital).
 * Ang division_head_employee_id ay idadagdag sa hiwalay na migration
 * dahil kailangan munang mag-exist ang employees table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('name');                            // "Medical Division"
            $table->string('code', 20)->nullable()->unique();  // "MED"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};
