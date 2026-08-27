<?php

namespace Tests\Feature\Admin;

use App\Enums\FunctionCategory;
use App\Enums\MeasureAnswer;
use App\Enums\RatingMeasure;
use App\Models\JobFunction;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Writing a rubric from the function form.
 *
 * A measure is all or nothing: blank means n/a, and once anything is typed
 * into it all five levels have to be there. A scale with a hole would refuse
 * to grade whatever falls in the gap, and say nothing about why.
 */
class RubricFormTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /** @return array<string, mixed> five complete numeric levels */
    private function numericBands(array $overrides = []): array
    {
        return array_replace([
            'answer' => MeasureAnswer::Number->value,
            'unit'   => '%',
            5 => ['description' => '100%', 'min' => 100, 'max' => ''],
            4 => ['description' => '90 to 99%', 'min' => 90, 'max' => 99.99],
            3 => ['description' => '80 to 89%', 'min' => 80, 'max' => 89.99],
            2 => ['description' => '70 to 79%', 'min' => 70, 'max' => 79.99],
            1 => ['description' => 'below 70%', 'min' => '', 'max' => 69.99],
        ], $overrides);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category'    => FunctionCategory::Core->value,
            'title'       => 'Complete DTR submitted',
            'position_id' => Position::factory()->create()->id,
        ], $overrides);
    }

    private function store(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload($overrides));
    }

    // -----------------------------------------------------------------
    // Writing one
    // -----------------------------------------------------------------

    public function test_a_rubric_is_saved_with_the_function(): void
    {
        $this->store(['rubric' => ['efficiency' => $this->numericBands()]])
            ->assertSessionHasNoErrors();

        $measure = JobFunction::first()->measures()->first();

        $this->assertSame(RatingMeasure::Efficiency, $measure->measure);
        $this->assertSame(MeasureAnswer::Number, $measure->answer);
        $this->assertSame('%', $measure->unit);
        $this->assertCount(5, $measure->bands);
    }

    public function test_an_open_ended_band_keeps_its_open_end(): void
    {
        $this->store(['rubric' => ['efficiency' => $this->numericBands()]]);

        $top = JobFunction::first()->measures()->first()->bands->firstWhere('level', 5);

        $this->assertSame('100.00', $top->min_value);
        $this->assertNull($top->max_value, 'A blank To means anything above From.');
    }

    /** A count is a percentage by construction and names no unit. */
    public function test_a_counted_measure_stores_no_unit(): void
    {
        $this->store([
            'rubric' => ['efficiency' => $this->numericBands(['answer' => MeasureAnswer::Count->value, 'unit' => '%'])],
        ]);

        $this->assertNull(JobFunction::first()->measures()->first()->unit);
    }

    public function test_a_measure_left_blank_is_not_rated(): void
    {
        $this->store(['rubric' => ['efficiency' => $this->numericBands()]]);

        $this->assertSame(['efficiency'], JobFunction::first()->measures->pluck('measure.value')->all());
    }

    public function test_a_function_can_still_be_saved_with_no_rubric_at_all(): void
    {
        $this->store()->assertSessionHasNoErrors();

        $this->assertFalse(JobFunction::first()->load('measures')->hasRubric());
    }

    // -----------------------------------------------------------------
    // Taking one back
    // -----------------------------------------------------------------

    /** Clearing a measure has to be possible, or a mistake is permanent. */
    public function test_clearing_a_measure_removes_its_rubric(): void
    {
        $this->store(['rubric' => ['efficiency' => $this->numericBands()]]);
        $function = JobFunction::first();

        $this->actingAs($this->admin())
            ->put(route('admin.functions.update', $function), $this->payload([
                'position_id' => $function->position_id,
                'rubric'      => ['efficiency' => ['answer' => MeasureAnswer::Number->value]],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $function->fresh()->measures);
    }

    /** Saving again replaces the five levels rather than adding five more. */
    public function test_saving_twice_does_not_double_the_bands(): void
    {
        $this->store(['rubric' => ['efficiency' => $this->numericBands()]]);
        $function = JobFunction::first();

        $this->actingAs($this->admin())->put(route('admin.functions.update', $function), $this->payload([
            'position_id' => $function->position_id,
            'rubric'      => ['efficiency' => $this->numericBands()],
        ]));

        $this->assertCount(5, $function->fresh()->measures()->first()->bands);
    }

    // -----------------------------------------------------------------
    // A half-written measure is refused
    // -----------------------------------------------------------------

    public function test_a_missing_description_is_refused(): void
    {
        $this->store([
            'rubric' => ['efficiency' => $this->numericBands([3 => ['description' => '', 'min' => 80, 'max' => 89]])],
        ])->assertSessionHasErrors('rubric.efficiency.3.description');

        $this->assertDatabaseCount('function_measures', 0);
    }

    public function test_a_numeric_level_with_no_range_is_refused(): void
    {
        $this->store([
            'rubric' => ['efficiency' => $this->numericBands([3 => ['description' => '80 to 89%', 'min' => '', 'max' => '']])],
        ])->assertSessionHasErrors('rubric.efficiency.3.min');
    }

    public function test_a_range_that_runs_backwards_is_refused(): void
    {
        $this->store([
            'rubric' => ['efficiency' => $this->numericBands([3 => ['description' => 'x', 'min' => 90, 'max' => 80]])],
        ])->assertSessionHasErrors('rubric.efficiency.3.min');
    }

    /** A descriptor measure has no ranges to be missing. */
    public function test_a_descriptor_measure_needs_only_its_five_descriptions(): void
    {
        $this->store(['rubric' => ['quality' => [
            'answer' => MeasureAnswer::Descriptor->value,
            5 => ['description' => 'No errors'],
            4 => ['description' => 'One error'],
            3 => ['description' => 'Two errors'],
            2 => ['description' => 'Three errors'],
            1 => ['description' => 'More than three'],
        ]]])->assertSessionHasNoErrors();

        $this->assertCount(5, JobFunction::first()->measures()->first()->bands);
    }

    // -----------------------------------------------------------------
    // The sentence
    // -----------------------------------------------------------------

    public function test_a_template_naming_no_figure_is_refused(): void
    {
        $this->store([
            'rubric'                  => ['efficiency' => $this->numericBands()],
            'accomplishment_template' => 'All DTRs were submitted on time',
        ])->assertSessionHasErrors('accomplishment_template');
    }

    public function test_a_template_naming_a_figure_is_kept(): void
    {
        $this->store([
            'rubric'                  => ['efficiency' => $this->numericBands()],
            'accomplishment_template' => '{e}% of DTR submitted every 5th day',
        ])->assertSessionHasNoErrors();

        $this->assertSame('{e}% of DTR submitted every 5th day', JobFunction::first()->accomplishment_template);
    }

    // -----------------------------------------------------------------
    // The form itself
    // -----------------------------------------------------------------

    public function test_the_form_offers_every_measure(): void
    {
        Position::factory()->create();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->getContent();

        foreach (RatingMeasure::cases() as $measure) {
            $this->assertStringContainsString('rubric[' . $measure->value . '][answer]', $html);
            $this->assertStringContainsString('rubric[' . $measure->value . '][5][description]', $html);
        }

        $this->assertStringContainsString('name="accomplishment_template"', $html);
    }

    /**
     * The panel's script is pushed from inside a component, inside a modal,
     * inside a loop. If the layout never renders that stack the whole rubric
     * sits there inert with no error anywhere.
     */
    public function test_the_panel_script_reaches_the_page(): void
    {
        Position::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->assertSee('function functionRubric()', false);
    }
}
