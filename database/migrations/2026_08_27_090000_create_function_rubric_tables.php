<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a function is graded, written down once on the catalog entry instead of
 * decided afresh by whoever happens to be rating.
 *
 * Two tables because there are two things:
 *
 *   function_measures      one row per measure a function is rated on. A
 *                          measure with no row is n/a for that function.
 *   function_rating_bands  the five levels of one measure. For a numeric
 *                          measure they carry the range that earns each mark.
 *
 * The template on job_functions is what turns the reported figure into the
 * sentence that goes on the form: "{e}% of DTR ... submitted every 5th day".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('function_measures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_function_id')->constrained()->cascadeOnDelete();

            $table->string('measure', 20);   // quality|efficiency|timeliness
            $table->string('answer', 20);    // descriptor|number|count
            $table->string('unit', 20)->nullable();  // null for descriptor and count

            $table->timestamps();

            // One rubric per measure per function.
            $table->unique(['job_function_id', 'measure']);
        });

        Schema::create('function_rating_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('function_measure_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('level');     // 5 down to 1
            $table->text('description');

            /*
             * The range that earns this level. Either end may be open: a blank
             * min means "anything below max", a blank max means "anything
             * above min". Bands are read from 5 down, so a timeliness scale is
             * written the other way round - level 5 up to 90 days, level 1
             * from 181 onwards.
             */
            $table->decimal('min_value', 12, 2)->nullable();
            $table->decimal('max_value', 12, 2)->nullable();

            $table->timestamps();

            $table->unique(['function_measure_id', 'level']);
        });

        Schema::table('job_functions', function (Blueprint $table) {
            $table->text('accomplishment_template')->nullable()->after('success_indicator');
        });
    }

    public function down(): void
    {
        Schema::table('job_functions', function (Blueprint $table) {
            $table->dropColumn('accomplishment_template');
        });

        Schema::dropIfExists('function_rating_bands');
        Schema::dropIfExists('function_measures');
    }
};
