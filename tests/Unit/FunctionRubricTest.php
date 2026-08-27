<?php

namespace Tests\Unit;

use App\Enums\FunctionCategory;
use App\Enums\MeasureAnswer;
use App\Enums\RatingMeasure;
use App\Models\FunctionMeasure;
use App\Models\FunctionRatingBand;
use App\Models\JobFunction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reading a reported figure off a function's rubric.
 *
 * Bands are tried from level 5 down, and either end of a band may be open.
 * That is what lets one scale run upwards - 100% is a 5 - and another run the
 * other way, where fewer days is better.
 */
class FunctionRubricTest extends TestCase
{
    use RefreshDatabase;

    private function functionWith(MeasureAnswer $answer, array $bands, RatingMeasure $measure = RatingMeasure::Efficiency): FunctionMeasure
    {
        $function = JobFunction::create([
            'category' => FunctionCategory::Core, 'title' => 'Submits the DTR', 'is_active' => true,
        ]);

        $rubric = FunctionMeasure::create([
            'job_function_id' => $function->id,
            'measure'         => $measure->value,
            'answer'          => $answer->value,
            'unit'            => $answer->hasUnit() ? '%' : null,
        ]);

        foreach ($bands as $level => [$description, $min, $max]) {
            FunctionRatingBand::create([
                'function_measure_id' => $rubric->id,
                'level'               => $level,
                'description'         => $description,
                'min_value'           => $min,
                'max_value'           => $max,
            ]);
        }

        return $rubric->load('bands');
    }

    /** A scale that runs upwards: more is better. */
    private function percentageScale(): FunctionMeasure
    {
        return $this->functionWith(MeasureAnswer::Number, [
            5 => ['100%', 100, null],
            4 => ['90 to 99%', 90, 99.99],
            3 => ['80 to 89%', 80, 89.99],
            2 => ['70 to 79%', 70, 79.99],
            1 => ['below 70%', null, 69.99],
        ]);
    }

    // -----------------------------------------------------------------
    // Reading a figure
    // -----------------------------------------------------------------

    public function test_a_figure_earns_the_band_it_falls_in(): void
    {
        $rubric = $this->percentageScale();

        $this->assertSame(5, $rubric->levelFor(100));
        $this->assertSame(4, $rubric->levelFor(95));
        $this->assertSame(3, $rubric->levelFor(80));
        $this->assertSame(1, $rubric->levelFor(12));
    }

    public function test_an_open_top_accepts_anything_above_it(): void
    {
        $this->assertSame(5, $this->percentageScale()->levelFor(140));
    }

    public function test_an_open_bottom_accepts_anything_below_it(): void
    {
        $this->assertSame(1, $this->percentageScale()->levelFor(0));
    }

    /**
     * Fewer days is better, so the scale is written the other way round. It
     * works because the bands are tried from 5 down, not because anything
     * knows which direction "better" runs.
     */
    public function test_a_scale_can_run_downwards(): void
    {
        $rubric = $this->functionWith(MeasureAnswer::Number, [
            5 => ['within 90 days', null, 90],
            4 => ['91 to 120 days', 91, 120],
            3 => ['121 to 150 days', 121, 150],
            2 => ['151 to 180 days', 151, 180],
            1 => ['181 days or more', 181, null],
        ], RatingMeasure::Timeliness);

        $this->assertSame(5, $rubric->levelFor(30));
        $this->assertSame(4, $rubric->levelFor(100));
        $this->assertSame(1, $rubric->levelFor(365));
    }

    public function test_a_figure_no_band_accepts_earns_nothing(): void
    {
        $rubric = $this->functionWith(MeasureAnswer::Number, [
            5 => ['100%', 100, 100],
        ]);

        $this->assertNull($rubric->levelFor(40));
    }

    /** A band with both ends open is the catch-all it looks like. */
    public function test_a_band_with_no_bounds_accepts_everything(): void
    {
        $rubric = $this->functionWith(MeasureAnswer::Number, [
            5 => ['perfect', 100, null],
            1 => ['anything else', null, null],
        ]);

        $this->assertSame(5, $rubric->levelFor(100));
        $this->assertSame(1, $rubric->levelFor(-40));
    }

    // -----------------------------------------------------------------
    // What a template may say
    // -----------------------------------------------------------------

    public function test_a_typed_measure_offers_its_own_placeholder(): void
    {
        $rubric = $this->percentageScale();

        $this->assertSame(['{e}'], $rubric->placeholders());
    }

    public function test_a_counted_measure_offers_its_parts_as_well(): void
    {
        $rubric = $this->functionWith(MeasureAnswer::Count, [5 => ['all of them', 100, null]]);

        $this->assertSame(['{e}', '{e_ratio}', '{e_count}', '{e_total}'], $rubric->placeholders());
    }

    public function test_a_descriptor_measure_offers_none(): void
    {
        $rubric = $this->functionWith(MeasureAnswer::Descriptor, [5 => ['excellent', null, null]]);

        $this->assertSame([], $rubric->placeholders());
    }

    /** One numeric measure means the unqualified form is unambiguous. */
    public function test_a_lone_numeric_measure_also_offers_the_plain_value(): void
    {
        $function = $this->percentageScale()->jobFunction->load('measures');

        $this->assertContains('{value}', $function->placeholders());
    }

    public function test_two_numeric_measures_do_not_offer_a_plain_value(): void
    {
        $rubric = $this->percentageScale();

        FunctionMeasure::create([
            'job_function_id' => $rubric->job_function_id,
            'measure'         => RatingMeasure::Quality->value,
            'answer'          => MeasureAnswer::Number->value,
            'unit'            => '%',
        ]);

        $placeholders = $rubric->jobFunction->fresh()->load('measures')->placeholders();

        $this->assertNotContains('{value}', $placeholders, 'With two figures, {value} names neither.');
        $this->assertContains('{e}', $placeholders);
        $this->assertContains('{q}', $placeholders);
    }

    public function test_a_function_without_a_rubric_says_so(): void
    {
        $function = JobFunction::create([
            'category' => FunctionCategory::Core, 'title' => 'Typed by hand', 'is_active' => true,
        ]);

        $this->assertFalse($function->load('measures')->hasRubric());
        $this->assertSame([], $function->placeholders());
    }
}
