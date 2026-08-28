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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Timeliness counted from the deadline: minus is early, plus is late.
 *
 * The figure is signed because the scale runs both ways - five days early and
 * five days late are different performances - but nobody wants to read "-5" in
 * the middle of a sentence. {t_when} says it in words instead, and gets the
 * sign, the plural and the on-time case right, none of which the person
 * writing the template should have to think about.
 */
class DaysFromDeadlineTest extends TestCase
{
    use RefreshDatabase;

    private AccomplishmentWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writer = app(AccomplishmentWriter::class);
    }

    private function function(
        string $template = 'Bid Evaluation Report submitted {t_when}',
        string $unit = 'days',
        MeasureAnswer $answer = MeasureAnswer::Number,
    ): JobFunction {
        $function = JobFunction::create([
            'category'                => FunctionCategory::Core,
            'title'                   => 'Prepared Bid Evaluation Report',
            'accomplishment_template' => $template,
            'is_active'               => true,
        ]);

        $measure = FunctionMeasure::create([
            'job_function_id' => $function->id,
            'measure'         => RatingMeasure::Timeliness->value,
            'answer'          => $answer->value,
            'unit'            => $answer === MeasureAnswer::Number ? $unit : null,
        ]);

        foreach ([[5, 'ahead', null, -5], [4, 'nearly', -4, -3], [3, 'on time', -2, 0],
            [2, 'a little late', 1, 7], [1, 'late', 8, null]] as [$level, $text, $min, $max]) {
            FunctionRatingBand::create([
                'function_measure_id' => $measure->id, 'level' => $level,
                'description' => $text, 'min_value' => $min, 'max_value' => $max,
            ]);
        }

        return $function->load('measures.bands');
    }

    private function sentenceFor(float $days, ?JobFunction $function = null): ?string
    {
        $function ??= $this->function();

        $item = IpcrItem::factory()->create([
            'ipcr_id'         => Ipcr::factory()->create()->id,
            'job_function_id' => $function->id,
            'category'        => FunctionCategory::Core,
        ]);

        $this->writer->apply($item, $function, ['timeliness' => ['value' => $days]]);

        return $item->fresh()->actual_accomplishment;
    }

    // -----------------------------------------------------------------
    // What the figure reads as
    // -----------------------------------------------------------------

    public static function readings(): array
    {
        return [
            'well ahead'   => [-12.0, 'Bid Evaluation Report submitted 12 days before the deadline'],
            'five early'   => [-5.0, 'Bid Evaluation Report submitted 5 days before the deadline'],
            'one early'    => [-1.0, 'Bid Evaluation Report submitted 1 day before the deadline'],
            'on the day'   => [0.0, 'Bid Evaluation Report submitted on time'],
            'one late'     => [1.0, 'Bid Evaluation Report submitted 1 day after the deadline'],
            'a week late'  => [7.0, 'Bid Evaluation Report submitted 7 days after the deadline'],
        ];
    }

    #[DataProvider('readings')]
    public function test_the_signed_figure_is_read_out_in_words(float $days, string $expected): void
    {
        $this->assertSame($expected, $this->sentenceFor($days));
    }

    /** Singular and plural, both ways round. Nobody submits "1 days" early. */
    public function test_one_day_is_singular(): void
    {
        $this->assertStringContainsString('1 day before', $this->sentenceFor(-1));
        $this->assertStringContainsString('1 day after', $this->sentenceFor(1));
        $this->assertStringContainsString('2 days before', $this->sentenceFor(-2));
    }

    /** The raw figure is still there for anyone who wants it, sign and all. */
    public function test_the_plain_placeholder_still_gives_the_number(): void
    {
        $function = $this->function(template: 'Submitted at {t} days from the deadline');

        $this->assertSame('Submitted at -5 days from the deadline', $this->sentenceFor(-5, $function));
    }

    /** And the mark still comes off the bands, unchanged by any of this. */
    public function test_the_mark_still_comes_from_the_bands(): void
    {
        $function = $this->function();

        $item = IpcrItem::factory()->create([
            'ipcr_id' => Ipcr::factory()->create()->id,
            'job_function_id' => $function->id, 'category' => FunctionCategory::Core,
        ]);

        $this->writer->apply($item, $function, ['timeliness' => ['value' => -5]]);

        $this->assertSame('5.00', $item->fresh()->timeliness_rating);
    }

    // -----------------------------------------------------------------
    // Where it is offered
    // -----------------------------------------------------------------

    public function test_it_is_offered_on_a_measure_counted_in_days(): void
    {
        $this->assertContains('{t_when}', $this->function()->placeholders());
    }

    /**
     * Not on a percentage. "5 % before the deadline" is nonsense, and a
     * placeholder that only makes sense sometimes should not be offered the
     * rest of the time.
     */
    public function test_it_is_not_offered_on_another_unit(): void
    {
        $this->assertNotContains('{t_when}', $this->function(unit: '%')->placeholders());
    }

    public function test_it_is_not_offered_on_a_counted_measure(): void
    {
        $function = $this->function(answer: MeasureAnswer::Count);

        $this->assertNotContains('{t_when}', $function->placeholders());
    }

    /** With one figure in the whole rubric, naming which is ceremony. */
    public function test_the_short_form_is_offered_when_there_is_only_one_figure(): void
    {
        $this->assertContains('{when}', $this->function()->placeholders());

        $this->assertSame(
            'Bid Evaluation Report submitted 5 days before the deadline',
            $this->sentenceFor(-5, $this->function(template: 'Bid Evaluation Report submitted {when}'))
        );
    }
}
