<?php

namespace Tests\Feature\Ipcr;

use App\Enums\FunctionCategory;
use App\Enums\MeasureAnswer;
use App\Enums\RatingMeasure;
use App\Models\FunctionMeasure;
use App\Models\FunctionRatingBand;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\JobFunction;
use App\Services\AccomplishmentWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The figure an employee reports becomes two things at once: the sentence on
 * the form, and the mark for that measure.
 *
 * Both come off the catalog function, so the same performance is written and
 * graded the same way whoever reports it - which is the whole point. A
 * function with no rubric is left exactly as it was.
 */
class AccomplishmentWriterTest extends TestCase
{
    use RefreshDatabase;

    private AccomplishmentWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writer = app(AccomplishmentWriter::class);
    }

    private function dtrFunction(MeasureAnswer $answer = MeasureAnswer::Number, ?string $template = null): JobFunction
    {
        $function = JobFunction::create([
            'category'                => FunctionCategory::Core,
            'title'                   => 'Complete DTR submitted',
            'success_indicator'       => '100% of DTR submitted every 5th day of the ensuing month',
            'accomplishment_template' => $template
                ?? '{e}% of DTR with complete attachments are submitted every 5th day of the ensuing month',
            'is_active'               => true,
        ]);

        $measure = FunctionMeasure::create([
            'job_function_id' => $function->id,
            'measure'         => RatingMeasure::Efficiency->value,
            'answer'          => $answer->value,
            'unit'            => $answer->hasUnit() ? '%' : null,
        ]);

        foreach ([[5, '100%', 100, null], [4, '90 to 99%', 90, 99.99], [3, '80 to 89%', 80, 89.99],
            [2, '70 to 79%', 70, 79.99], [1, 'below 70%', null, 69.99]] as [$level, $text, $min, $max]) {
            FunctionRatingBand::create([
                'function_measure_id' => $measure->id, 'level' => $level,
                'description' => $text, 'min_value' => $min, 'max_value' => $max,
            ]);
        }

        return $function->load('measures.bands');
    }

    private function line(JobFunction $function): IpcrItem
    {
        return IpcrItem::factory()->create([
            'ipcr_id'         => Ipcr::factory()->create()->id,
            'job_function_id' => $function->id,
            'category'        => FunctionCategory::Core,
            'weight'          => 100,
        ]);
    }

    // -----------------------------------------------------------------
    // A typed figure
    // -----------------------------------------------------------------

    public function test_the_figure_writes_the_sentence(): void
    {
        $function = $this->dtrFunction();
        $item = $this->line($function);

        $this->writer->apply($item, $function, ['efficiency' => ['value' => 100]]);

        $this->assertSame(
            '100% of DTR with complete attachments are submitted every 5th day of the ensuing month',
            $item->fresh()->actual_accomplishment
        );
    }

    public function test_the_figure_sets_the_mark(): void
    {
        $function = $this->dtrFunction();
        $item = $this->line($function);

        $this->writer->apply($item, $function, ['efficiency' => ['value' => 95]]);

        $this->assertSame('4.00', $item->fresh()->efficiency_rating);
    }

    /** A measure the function is not rated on stays n/a, not zero. */
    public function test_the_measures_not_in_the_rubric_are_left_alone(): void
    {
        $function = $this->dtrFunction();
        $item = $this->line($function);

        $this->writer->apply($item, $function, ['efficiency' => ['value' => 100]]);

        $item->refresh();
        $this->assertNull($item->quality_rating);
        $this->assertNull($item->timeliness_rating);
    }

    public function test_a_whole_number_is_not_written_with_trailing_zeros(): void
    {
        $function = $this->dtrFunction(template: 'Reached {e} of the target');
        $item = $this->line($function);

        $this->writer->apply($item, $function, ['efficiency' => ['value' => 100]]);

        $this->assertSame('Reached 100 of the target', $item->fresh()->actual_accomplishment);
    }

    public function test_a_fraction_keeps_its_decimals(): void
    {
        $function = $this->dtrFunction(template: 'Reached {e}%');
        $item = $this->line($function);

        $this->writer->apply($item, $function, ['efficiency' => ['value' => 99.5]]);

        $this->assertSame('Reached 99.5%', $item->fresh()->actual_accomplishment);
    }

    // -----------------------------------------------------------------
    // A count
    // -----------------------------------------------------------------

    public function test_a_count_is_graded_on_the_percentage_it_makes(): void
    {
        $function = $this->dtrFunction(MeasureAnswer::Count);
        $item = $this->line($function);

        $this->writer->apply($item, $function, ['efficiency' => ['count' => 12, 'total' => 12]]);

        $this->assertSame('5.00', $item->fresh()->efficiency_rating);
    }

    public function test_a_count_can_say_twelve_of_twelve(): void
    {
        $function = $this->dtrFunction(MeasureAnswer::Count, 'Submitted {e_ratio} monthly reports');
        $item = $this->line($function);

        $this->writer->apply($item, $function, ['efficiency' => ['count' => 11, 'total' => 12]]);

        $this->assertSame('Submitted 11 of 12 monthly reports', $item->fresh()->actual_accomplishment);
    }

    public function test_the_parts_of_a_count_are_kept(): void
    {
        $function = $this->dtrFunction(MeasureAnswer::Count);
        $item = $this->line($function);

        $this->writer->apply($item, $function, ['efficiency' => ['count' => 11, 'total' => 12]]);

        $reported = $item->fresh()->measures->firstWhere('measure', RatingMeasure::Efficiency);

        $this->assertSame('11.00', $reported->reported_count);
        $this->assertSame('12.00', $reported->reported_total);
        $this->assertSame('91.67', $reported->value, 'The bands are read against the percentage.');
    }

    /** Dividing by nothing is not an error, it is nothing reported. */
    public function test_a_count_out_of_zero_reports_nothing(): void
    {
        $function = $this->dtrFunction(MeasureAnswer::Count);
        $item = $this->line($function);

        $this->writer->apply($item, $function, ['efficiency' => ['count' => 5, 'total' => 0]]);

        $this->assertNull($item->fresh()->efficiency_rating);
    }

    // -----------------------------------------------------------------
    // The plain placeholder
    // -----------------------------------------------------------------

    public function test_one_numeric_measure_answers_to_the_plain_value(): void
    {
        $function = $this->dtrFunction(template: 'Achieved {value}%');
        $item = $this->line($function);

        $this->writer->apply($item, $function, ['efficiency' => ['value' => 88]]);

        $this->assertSame('Achieved 88%', $item->fresh()->actual_accomplishment);
    }

    // -----------------------------------------------------------------
    // Leaving it out
    // -----------------------------------------------------------------

    public function test_reporting_nothing_leaves_the_measure_unmarked(): void
    {
        $function = $this->dtrFunction();
        $item = $this->line($function);

        $this->writer->apply($item, $function, []);

        $this->assertNull($item->fresh()->efficiency_rating);
    }

    /** Clearing a figure clears its mark rather than leaving a stale one. */
    public function test_taking_a_figure_back_takes_the_mark_with_it(): void
    {
        $function = $this->dtrFunction();
        $item = $this->line($function);

        $this->writer->apply($item, $function, ['efficiency' => ['value' => 100]]);
        $this->assertSame('5.00', $item->fresh()->efficiency_rating);

        $this->writer->apply($item->fresh(), $function, ['efficiency' => ['value' => '']]);

        $item->refresh();
        $this->assertNull($item->efficiency_rating);
        $this->assertCount(0, $item->measures);
    }

    /** A function with a rubric but no template keeps what was typed. */
    public function test_no_template_leaves_the_written_accomplishment_alone(): void
    {
        $function = $this->dtrFunction(template: '');
        $item = $this->line($function);
        $item->update(['actual_accomplishment' => 'Written out by hand']);

        $this->writer->apply($item, $function, ['efficiency' => ['value' => 100]]);

        $item->refresh();
        $this->assertSame('Written out by hand', $item->actual_accomplishment);
        $this->assertSame('5.00', $item->efficiency_rating, 'The mark is still worked out.');
    }
}
