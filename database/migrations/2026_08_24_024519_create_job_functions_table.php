<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master catalog ng mga function na pwedeng piliin kapag gumagawa ng IPCR.
 *
 * Tandaan: `job_functions` ang pangalan, HINDI `functions` -- reserved word
 * ang `function` sa PHP kaya hindi pwedeng gawing `class Function` ang model.
 *
 * Saan nakakabit:
 *   core      -> position_id     (galing sa plantilla position)
 *   strategic -> designation_id  (galing sa designation/OIC role)
 *   support   -> designation_id  (galing sa designation/OIC role)
 *   common    -> parehong null   (open pool, lahat pwedeng pumili)
 *
 * Ang catalog na ito ay PANUKALA lang - manual pa rin ang pag-add ng
 * empleyado ng mga item papunta sa aktwal niyang IPCR. Walang auto-populate.
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
            $table->text('title');                               // ang output / objective
            $table->text('success_indicator')->nullable();       // target + measure
            $table->decimal('default_weight', 5, 2)->nullable(); // mungkahing timbang, %
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
