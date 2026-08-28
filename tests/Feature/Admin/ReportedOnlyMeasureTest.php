<?php

namespace Tests\Feature\Admin;

use App\Enums\FunctionCategory;
use App\Enums\MeasureAnswer;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\JobFunction;
use App\Models\Position;
use App\Models\User;
use App\Services\AccomplishmentWriter;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A figure that is printed but not graded.
 *
 * Plenty of real functions carry a number in their wording that nobody rates.
 * "100% of reports submitted within 12 calendar days" is one sentence with two
 * figures in it, and only the days earn a mark - the percentage is there
 * because the sentence reads wrong without it.
 *
 * Before this, the only way to get a figure into the wording was to grade it,
 * which meant inventing five levels for something nobody wanted a mark for.
 */
class ReportedOnlyMeasureTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /** Five complete levels, for the measure that is graded. */
    private function bands(array $overrides = []): array
    {
        return array_replace([
            'answer' => MeasureAnswer::Number->value,
            'unit'   => 'days',
            5 => ['description' => 'Well ahead', 'min' => '', 'max' => 8],
            4 => ['description' => 'Ahead', 'min' => 9, 'max' => 10],
            3 => ['description' => 'On time', 'min' => 11, 'max' => 12],
            2 => ['description' => 'A little late', 'min' => 13, 'max' => 19],
            1 => ['description' => 'Late', 'min' => 20, 'max' => ''],
        ], $overrides);
    }

    /** A measure answered by a figure, with no levels behind it. */
    private function reportedOnly(MeasureAnswer $answer = MeasureAnswer::Count): array
    {
        return ['answer' => $answer->value, 'reported_only' => '1'];
    }

    private function store(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())->post(route('admin.functions.store'), array_merge([
            'category'    => FunctionCategory::Core->value,
            'applies_to'  => 'position',
            'title'       => 'Post-Qualification Evaluation Report',
            'position_id' => Position::factory()->create()->id,
        ], $overrides));
    }

    // -----------------------------------------------------------------
    // Saving one
    // -----------------------------------------------------------------

    public function test_a_measure_can_be_reported_without_being_graded(): void
    {
        $this->store([
            'rubric' => [
                'efficiency' => $this->reportedOnly(),
                'timeliness' => $this->bands(),
            ],
        ])->assertSessionHasNoErrors();

        $function = JobFunction::first()->load('measures.bands');

        $efficiency = $function->measures->firstWhere('measure.value', 'efficiency');
        $timeliness = $function->measures->firstWhere('measure.value', 'timeliness');

        $this->assertCount(0, $efficiency->bands, 'Nothing to grade it against.');
        $this->assertCount(5, $timeliness->bands);
    }

    /** No levels means no levels to be missing, so none are demanded. */
    public function test_it_is_not_asked_for_five_levels(): void
    {
        $this->store([
            'rubric' => [
                'quality'    => $this->reportedOnly(MeasureAnswer::Number),
                'timeliness' => $this->bands(),
            ],
        ])->assertSessionHasNoErrors();
    }

    /** Its placeholders are offered, which is the whole point of it. */
    public function test_its_figure_can_be_named_in_the_wording(): void
    {
        $this->store([
            'rubric' => [
                'efficiency' => $this->reportedOnly(),
                'timeliness' => $this->bands(),
            ],
            'accomplishment_template' => '{e}% ({e_ratio}) submitted within {t} calendar days',
        ])->assertSessionHasNoErrors();
    }

    /**
     * At least one measure has to be graded.
     *
     * A function whose every measure is reported-only can never earn a mark:
     * the rubric will not give one and the employee is not offered the box,
     * so the line could be neither rated nor submitted.
     */
    public function test_a_rubric_that_grades_nothing_is_refused(): void
    {
        $this->store([
            'rubric' => ['efficiency' => $this->reportedOnly()],
        ])->assertSessionHasErrors('rubric');
    }

    // -----------------------------------------------------------------
    // On the IPCR
    // -----------------------------------------------------------------

    public function test_the_figure_reaches_the_sentence_and_earns_no_mark(): void
    {
        $this->store([
            'rubric' => [
                'efficiency' => $this->reportedOnly(),
                'timeliness' => $this->bands(),
            ],
            'accomplishment_template' => '{e}% ({e_ratio}) Post-Qualification Evaluation Report submitted within {t} calendar days',
        ])->assertSessionHasNoErrors();

        $function = JobFunction::first()->load('measures.bands');

        $item = IpcrItem::factory()->create([
            'ipcr_id'         => Ipcr::factory()->create()->id,
            'job_function_id' => $function->id,
            'category'        => FunctionCategory::Core,
        ]);

        app(AccomplishmentWriter::class)->apply($item, $function, [
            'efficiency' => ['count' => 7, 'total' => 7],
            'timeliness' => ['value' => 12],
        ]);

        $item = $item->fresh();

        $this->assertSame(
            '100% (7/7) Post-Qualification Evaluation Report submitted within 12 calendar days',
            $item->actual_accomplishment
        );

        $this->assertNull($item->efficiency_rating, 'Printed, not graded.');
        $this->assertSame('3.00', $item->timeliness_rating);
        $this->assertSame('3.000', $item->average_rating, 'The ungraded figure stays out of the average.');
    }

    /** And a figure with nothing to grade it against is never called ungradable. */
    public function test_the_reported_figure_is_not_refused_as_ungradable(): void
    {
        $this->store([
            'rubric' => [
                'efficiency' => $this->reportedOnly(),
                'timeliness' => $this->bands(),
            ],
        ]);

        $function = JobFunction::first()->load('measures.bands');

        $this->assertSame([], app(AccomplishmentWriter::class)->ungradable($function, [
            'efficiency' => ['count' => 7, 'total' => 7],
            'timeliness' => ['value' => 12],
        ]));
    }

    // -----------------------------------------------------------------
    // The form
    // -----------------------------------------------------------------

    public function test_the_form_offers_the_choice(): void
    {
        Position::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->assertSee('rubric[efficiency][reported_only]', false);
    }

    // -----------------------------------------------------------------
    // The typo that started this
    // -----------------------------------------------------------------

    /**
     * A mismatched bracket used to be reported as no placeholder at all, which
     * is a baffling thing to be told when you can see one on the screen.
     */
    public function test_a_mismatched_bracket_says_so(): void
    {
        $this->store([
            'rubric'                  => ['timeliness' => $this->bands()],
            'accomplishment_template' => '100% submitted within {t) calendar days',
        ])->assertInvalid(['accomplishment_template' => 'closing brace']);
    }
}
