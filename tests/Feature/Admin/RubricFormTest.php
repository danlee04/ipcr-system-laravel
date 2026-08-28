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
            'applies_to'  => 'position',
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

    /**
     * A ratio belongs to a counted measure and nothing else.
     *
     * This one used to pass and then print "{t_ratio}" in the middle of the
     * sentence on somebody's IPCR: the template was checked for having at
     * least one good placeholder, not for every placeholder being good.
     */
    public function test_a_ratio_on_a_typed_number_is_refused(): void
    {
        $this->store([
            'rubric'                  => ['timeliness' => $this->numericBands(['unit' => 'days'])],
            'accomplishment_template' => 'Submitted within {t} days ({t_ratio})',
        ])->assertSessionHasErrors('accomplishment_template');
    }

    public function test_the_same_ratio_is_fine_once_the_measure_is_counted(): void
    {
        $this->store([
            'rubric'                  => ['timeliness' => $this->numericBands(['answer' => MeasureAnswer::Count->value])],
            'accomplishment_template' => '{t}% ({t_ratio}) submitted on time',
        ])->assertSessionHasNoErrors();
    }

    /** A misspelt placeholder is the same mistake wearing a different hat. */
    public function test_a_placeholder_that_does_not_exist_is_refused(): void
    {
        $this->store([
            'rubric'                  => ['efficiency' => $this->numericBands()],
            'accomplishment_template' => '{e}% of DTR submitted in {days} days',
        ])->assertSessionHasErrors('accomplishment_template');
    }

    /** And the error says which one, and what there was to choose from. */
    public function test_the_error_names_the_offending_placeholder(): void
    {
        $this->store([
            'rubric'                  => ['efficiency' => $this->numericBands()],
            'accomplishment_template' => '{e}% within {t} days',
        ])->assertInvalid([
            // Which one is wrong, and what there was to choose from.
            'accomplishment_template' => '{t}',
        ]);
    }

    /**
     * The message names the setting to change, not only the list to pick from.
     *
     * The list alone leaves you to work backwards from what is allowed to what
     * you would have to have done differently, which is the wrong way round.
     */
    public function test_the_error_says_how_to_get_what_was_asked_for(): void
    {
        $this->store([
            'rubric'                  => ['timeliness' => $this->numericBands(['answer' => MeasureAnswer::Count->value])],
            'accomplishment_template' => '{e}% ({e_ratio}) submitted {t_when}',
        ])->assertInvalid([
            'accomplishment_template' => 'Efficiency is not graded on a figure yet',
        ]);
    }

    public function test_the_error_explains_a_days_reading_on_a_counted_measure(): void
    {
        $this->store([
            'rubric'                  => ['timeliness' => $this->numericBands(['answer' => MeasureAnswer::Count->value])],
            'accomplishment_template' => 'Submitted {t_when}',
        ])->assertInvalid([
            'accomplishment_template' => 'not answered by a number in days',
        ]);
    }

    public function test_the_error_explains_a_ratio_on_a_typed_number(): void
    {
        $this->store([
            'rubric'                  => ['timeliness' => $this->numericBands(['unit' => 'days'])],
            'accomplishment_template' => 'Submitted {t} days ({t_ratio})',
        ])->assertInvalid([
            'accomplishment_template' => 'Only a counted measure has parts to name',
        ]);
    }

    /** One measure, one reason - not the same sentence twice over. */
    public function test_two_placeholders_on_the_same_measure_are_explained_once(): void
    {
        $response = $this->store([
            'rubric'                  => ['timeliness' => $this->numericBands(['unit' => 'days'])],
            'accomplishment_template' => '{e}% ({e_ratio}) submitted {t_when}',
        ]);

        // The bag comes back in more than one shape depending on how the
        // session was flashed; flattening it sidesteps the question.
        $message = implode(' ', \Illuminate\Support\Arr::flatten(
            (array) $response->getSession()->get('errors')
        ));

        $this->assertSame(1, substr_count($message, 'Efficiency is not graded on a figure yet'));
    }

    /** A scale in days may say its reading in words. */
    public function test_the_when_placeholder_is_accepted_on_a_measure_in_days(): void
    {
        $this->store([
            'rubric'                  => ['timeliness' => $this->numericBands(['unit' => 'days'])],
            'accomplishment_template' => 'Bid Evaluation Report submitted {t_when}',
        ])->assertSessionHasNoErrors();
    }

    /** "5 % before the deadline" is nonsense, so it is refused on a percentage. */
    public function test_the_when_placeholder_is_refused_on_another_unit(): void
    {
        $this->store([
            'rubric'                  => ['timeliness' => $this->numericBands(['unit' => '%'])],
            'accomplishment_template' => 'Submitted {t_when}',
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
     * The rubric puts a four-column grid inside the modal, three times over.
     * At Breeze's 2xl the From and To boxes are barely wide enough for a
     * number.
     */
    public function test_the_form_opens_in_a_wide_modal(): void
    {
        Position::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->assertSee('sm:max-w-4xl', false);
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
            ->assertSee('function functionRubric(', false);
    }

    /**
     * A placeholder the rubric cannot fill is flagged as it is typed.
     *
     * The list of what is available was already under the box, and people
     * still pasted {e} into a rubric with no Efficiency - a quiet list is easy
     * to read past. This says it back to them, and says which panel to change.
     */
    public function test_the_form_flags_an_unusable_placeholder_before_saving(): void
    {
        Position::factory()->create();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('x-text="unusable.join(\' \')"', $html);
        $this->assertStringContainsString('is not graded on a figure yet.', $html);
        $this->assertStringContainsString('is not answered by counting out of a total.', $html);
        $this->assertStringContainsString('is not answered by a number in days.', $html);
    }

    /**
     * The list says which measures are graded, and nothing about how.
     *
     * Three five-level scales flattened into a table cell are unreadable, and
     * unreadable in every row at once. The levels live in the editor.
     */
    public function test_the_list_names_the_measures_without_their_levels(): void
    {
        $this->store([
            'title'  => 'Complete DTR submitted',
            'rubric' => ['efficiency' => $this->numericBands([5 => ['description' => 'Perfect score', 'min' => 100, 'max' => '']])],
        ])->assertSessionHasNoErrors();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Rated On', $html);
        $this->assertStringContainsString('Efficiency — typing a number in %', $html);

        // The band's own words appear once, inside the editor - not in the row.
        $this->assertSame(1, substr_count($html, 'Perfect score'));
    }

    /** A function nobody wrote a rubric for says so, rather than sitting blank. */
    public function test_a_function_with_no_rubric_is_marked_as_graded_by_hand(): void
    {
        $this->store(['title' => 'Attends meetings'])->assertSessionHasNoErrors();

        $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->assertSee('By hand');
    }

    /** The wording already saved has to survive being bound to Alpine. */
    public function test_an_existing_template_is_still_in_the_box(): void
    {
        $this->store([
            'rubric'                  => ['efficiency' => $this->numericBands()],
            'accomplishment_template' => '{e}% of DTR submitted every 5th day',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->assertSee('{e}% of DTR submitted every 5th day', false);
    }
}
