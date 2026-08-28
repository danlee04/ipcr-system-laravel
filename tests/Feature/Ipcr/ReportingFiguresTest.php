<?php

namespace Tests\Feature\Ipcr;

use App\Enums\FunctionCategory;
use App\Enums\IpcrMode;
use App\Enums\IpcrStatus;
use App\Enums\MeasureAnswer;
use App\Enums\RatingMeasure;
use App\Models\Division;
use App\Models\Employee;
use App\Models\FunctionMeasure;
use App\Models\FunctionRatingBand;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\JobFunction;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reporting an accomplishment is typing a figure.
 *
 * The employee says what they did in numbers - 11 of 12 reports, 95% on time -
 * and the rubric on the catalog function turns that into both the sentence on
 * the form and the mark for the measure. Nobody writes the sentence by hand
 * and nobody chooses the mark, so the same performance reads and scores the
 * same way whoever reports it.
 */
class ReportingFiguresTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Ipcr $ipcr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->employeeUser();
        $this->ipcr = Ipcr::factory()->create([
            'employee_id' => $this->owner->employee->id,
            'status'      => IpcrStatus::Draft,
            'mode'        => IpcrMode::WithAccomplishment,
        ]);
    }

    private function employeeUser(): User
    {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);

        return $user->fresh();
    }

    /**
     * A function graded on one measure.
     *
     * The bands are the CSC's usual percentage ladder, which is what makes 95
     * a 4 and 100 a 5.
     */
    private function graded(
        RatingMeasure $measure = RatingMeasure::Efficiency,
        MeasureAnswer $answer = MeasureAnswer::Number,
        ?string $template = '{value}% of DTR submitted every 5th day',
    ): JobFunction {
        $function = JobFunction::create([
            'category'                => FunctionCategory::Core,
            'title'                   => 'Complete DTR submitted',
            'accomplishment_template' => $template,
            'is_active'               => true,
        ]);

        $row = FunctionMeasure::create([
            'job_function_id' => $function->id,
            'measure'         => $measure->value,
            'answer'          => $answer->value,
            'unit'            => $answer->hasUnit() ? '%' : null,
        ]);

        foreach ([[5, '100%', 100, null], [4, '90 to 99%', 90, 99.99], [3, '80 to 89%', 80, 89.99],
            [2, '70 to 79%', 70, 79.99], [1, '60 to 69%', 60, 69.99]] as [$level, $text, $min, $max]) {
            FunctionRatingBand::create([
                'function_measure_id' => $row->id, 'level' => $level,
                'description' => $text, 'min_value' => $min, 'max_value' => $max,
            ]);
        }

        return $function->load('measures.bands');
    }

    private function line(?JobFunction $function = null, array $attributes = []): IpcrItem
    {
        return IpcrItem::factory()->create(array_merge([
            'ipcr_id'         => $this->ipcr->id,
            'job_function_id' => $function?->id,
            'category'        => FunctionCategory::Core,
            'output'          => 'Complete DTR submitted',
            'weight'          => 100,
        ], $attributes));
    }

    private function report(IpcrItem $item, array $reported, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->owner)->put(
            route('ipcrs.items.update', [$this->ipcr, $item]),
            array_merge([
                'output'   => $item->output,
                'weight'   => $item->weight,
                'reported' => $reported,
            ], $overrides)
        );
    }

    // -----------------------------------------------------------------
    // One figure, two results
    // -----------------------------------------------------------------

    public function test_a_reported_figure_writes_the_sentence(): void
    {
        $item = $this->line($this->graded());

        $this->report($item, ['efficiency' => ['value' => 95]])->assertSessionHasNoErrors();

        $this->assertSame('95% of DTR submitted every 5th day', $item->fresh()->actual_accomplishment);
    }

    public function test_the_same_figure_sets_the_mark(): void
    {
        $item = $this->line($this->graded());

        $this->report($item, ['efficiency' => ['value' => 95]]);

        $this->assertSame('4.00', $item->fresh()->efficiency_rating);
    }

    /** The figure itself is kept, so the form can show what was reported. */
    public function test_the_figure_is_kept_beside_the_line(): void
    {
        $item = $this->line($this->graded());

        $this->report($item, ['efficiency' => ['value' => 95]]);

        $this->assertDatabaseHas('ipcr_item_measures', [
            'ipcr_item_id' => $item->id, 'measure' => 'efficiency', 'value' => 95,
        ]);
    }

    public function test_a_counted_measure_takes_two_figures(): void
    {
        $item = $this->line($this->graded(
            answer: MeasureAnswer::Count,
            template: '{ratio} reports submitted on time',
        ));

        $this->report($item, ['efficiency' => ['count' => 11, 'total' => 12]]);

        $item = $item->fresh();
        $this->assertSame('11/12 reports submitted on time', $item->actual_accomplishment);
        $this->assertSame('4.00', $item->efficiency_rating, '11 of 12 is 91.67%, which is a 4.');
    }

    public function test_a_picked_descriptor_is_the_mark(): void
    {
        $item = $this->line($this->graded(
            measure: RatingMeasure::Quality,
            answer: MeasureAnswer::Descriptor,
            template: null,
        ));

        $this->report($item, ['quality' => ['value' => 3]], ['actual_accomplishment' => 'Two errors found']);

        $item = $item->fresh();
        $this->assertSame('3.00', $item->quality_rating);
        $this->assertSame('Two errors found', $item->actual_accomplishment, 'With no template the employee keeps their own words.');
    }

    // -----------------------------------------------------------------
    // Nothing reported
    // -----------------------------------------------------------------

    public function test_a_measure_left_blank_stays_not_applicable(): void
    {
        $item = $this->line($this->graded());

        $this->report($item, ['efficiency' => ['value' => 95]]);
        $this->report($item, ['efficiency' => ['value' => '']]);

        $this->assertNull($item->fresh()->efficiency_rating, 'Blank is n/a, not zero.');
        $this->assertDatabaseCount('ipcr_item_measures', 0);
    }

    public function test_a_line_with_no_rubric_keeps_the_words_the_employee_typed(): void
    {
        $item = $this->line();

        $this->report($item, ['efficiency' => ['value' => 95]], [
            'actual_accomplishment' => 'Handled every walk-in request',
        ])->assertSessionHasNoErrors();

        $item = $item->fresh();
        $this->assertSame('Handled every walk-in request', $item->actual_accomplishment);
        $this->assertNull($item->efficiency_rating);
    }

    /** Targets only means no accomplishment at all, figures included. */
    public function test_figures_are_ignored_when_the_ipcr_holds_targets_only(): void
    {
        $this->ipcr->update(['mode' => IpcrMode::TargetsOnly]);
        $item = $this->line($this->graded());

        $this->report($item, ['efficiency' => ['value' => 95]])->assertSessionHasNoErrors();

        $item = $item->fresh();
        $this->assertNull($item->actual_accomplishment);
        $this->assertNull($item->efficiency_rating);
    }

    // -----------------------------------------------------------------
    // Figures that cannot be graded
    // -----------------------------------------------------------------

    /**
     * A figure no band accepts would otherwise be stored with no mark at all,
     * and the hole would only surface when the assessor could not finish.
     */
    public function test_a_figure_outside_every_level_is_refused(): void
    {
        $item = $this->line($this->graded());

        $this->report($item, ['efficiency' => ['value' => 20]])
            ->assertSessionHas('error');

        $item = $item->fresh();
        $this->assertNull($item->efficiency_rating);
        $this->assertNull($item->actual_accomplishment);
        $this->assertDatabaseCount('ipcr_item_measures', 0);
    }

    /** The rest of the line is not saved either - the whole edit is refused. */
    public function test_the_refused_edit_changes_nothing_else(): void
    {
        $item = $this->line($this->graded(), ['weight' => 100]);

        $this->report($item, ['efficiency' => ['value' => 20]], ['output' => 'Something else']);

        $this->assertSame('Complete DTR submitted', $item->fresh()->output);
    }

    public function test_something_that_is_not_a_number_is_refused(): void
    {
        $item = $this->line($this->graded());

        $this->report($item, ['efficiency' => ['value' => 'quite good']])
            ->assertSessionHasErrors('reported.efficiency.value');
    }

    // -----------------------------------------------------------------
    // The assessor
    // -----------------------------------------------------------------

    /**
     * The whole point of a rubric is that the figure decides the mark. If the
     * assessor could type over it the two would disagree and the form would
     * say one thing while the sentence beside it said another.
     */
    public function test_the_assessor_cannot_type_over_a_mark_the_rubric_produced(): void
    {
        $assessor = $this->assessorFor($this->ipcr);
        $item = $this->line($this->graded());
        $this->report($item, ['efficiency' => ['value' => 95]]);

        $this->ipcr->update(['status' => IpcrStatus::Submitted, 'submitted_at' => now()]);

        $this->actingAs($assessor)->put(route('ipcrs.ratings.update', $this->ipcr), [
            'ratings' => [$item->id => ['efficiency' => 1]],
        ]);

        $this->assertSame('4.00', $item->fresh()->efficiency_rating);
    }

    /** A measure the rubric says nothing about is still the assessor's. */
    public function test_the_assessor_still_marks_the_measures_the_rubric_leaves_out(): void
    {
        $assessor = $this->assessorFor($this->ipcr);
        $item = $this->line($this->graded());
        $this->report($item, ['efficiency' => ['value' => 95]]);

        $this->ipcr->update(['status' => IpcrStatus::Submitted, 'submitted_at' => now()]);

        $this->actingAs($assessor)->put(route('ipcrs.ratings.update', $this->ipcr), [
            'ratings' => [$item->id => ['quality' => 5, 'efficiency' => 1]],
        ]);

        $this->assertSame('5.00', $item->fresh()->quality_rating);
    }

    /** Nor is the assessor shown a box that would do nothing if they used it. */
    public function test_the_assessor_is_shown_the_mark_rather_than_a_box(): void
    {
        $assessor = $this->assessorFor($this->ipcr);
        $item = $this->line($this->graded());
        $this->report($item, ['efficiency' => ['value' => 95]]);

        $this->ipcr->update(['status' => IpcrStatus::Submitted, 'submitted_at' => now()]);

        $html = $this->actingAs($assessor)
            ->get(route('ipcrs.show', $this->ipcr))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString("ratings[{$item->id}][efficiency]", $html);
        $this->assertStringContainsString("ratings[{$item->id}][quality]", $html);
    }

    private function assessorFor(Ipcr $ipcr): User
    {
        $division = Division::factory()->create();
        $section = Section::factory()->create(['division_id' => $division->id]);

        $user = User::factory()->create();
        $assessor = Employee::factory()->create(['user_id' => $user->id, 'section_id' => $section->id]);
        $section->update(['section_head_employee_id' => $assessor->id]);

        $ipcr->update(['assessor_employee_id' => $assessor->id]);

        return $user->fresh();
    }

    // -----------------------------------------------------------------
    // The form
    // -----------------------------------------------------------------

    public function test_the_form_asks_for_a_figure_rather_than_a_sentence(): void
    {
        $this->line($this->graded());

        $html = $this->actingAs($this->owner)
            ->get(route('ipcrs.show', $this->ipcr))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('reported[efficiency][value]', $html);
        $this->assertStringContainsString('90 to 99%', $html, 'The levels are shown, so the employee knows what a figure earns.');
    }

    public function test_a_counted_measure_asks_for_both_parts(): void
    {
        $this->line($this->graded(answer: MeasureAnswer::Count));

        $html = $this->actingAs($this->owner)
            ->get(route('ipcrs.show', $this->ipcr))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('reported[efficiency][count]', $html);
        $this->assertStringContainsString('reported[efficiency][total]', $html);
    }

    public function test_a_line_with_no_rubric_still_offers_the_plain_field(): void
    {
        $this->line();

        $this->actingAs($this->owner)
            ->get(route('ipcrs.show', $this->ipcr))
            ->assertOk()
            ->assertSee('name="actual_accomplishment"', false);
    }
}
