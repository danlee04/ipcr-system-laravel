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
 * The employee rates their own IPCR. The supervisors approve it.
 *
 * That is how the hospital works, and the system used to have it the other way
 * round: the Section Head typed every mark at assessment time, having not done
 * the work and not seen the evidence. The two stages are still there and still
 * mean something - an approver who disagrees returns the IPCR - but nobody
 * grades somebody else's line from a table of empty boxes.
 *
 * A measure the catalog rubric grades is still not typed by anyone. The figure
 * decides it, and that is true whoever is looking.
 */
class SelfRatingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $sectionHead;

    private Ipcr $ipcr;

    protected function setUp(): void
    {
        parent::setUp();

        $division = Division::factory()->create();
        $section = Section::factory()->create(['division_id' => $division->id]);

        $this->sectionHead = $this->employeeUser(['section_id' => $section->id]);
        $section->update(['section_head_employee_id' => $this->sectionHead->employee->id]);

        $divisionHead = Employee::factory()->create(['division_id' => $division->id]);
        $division->update(['division_head_employee_id' => $divisionHead->id]);

        $this->owner = $this->employeeUser(['section_id' => $section->id, 'division_id' => $division->id]);

        $this->ipcr = Ipcr::factory()->create([
            'employee_id' => $this->owner->employee->id,
            'status'      => IpcrStatus::Draft,
            'mode'        => IpcrMode::WithAccomplishment,
        ]);
    }

    private function employeeUser(array $attributes = []): User
    {
        $user = User::factory()->create();
        Employee::factory()->create(array_merge(['user_id' => $user->id], $attributes));

        return $user->fresh();
    }

    private function line(?JobFunction $function = null): IpcrItem
    {
        return IpcrItem::factory()->create([
            'ipcr_id'         => $this->ipcr->id,
            'job_function_id' => $function?->id,
            'category'        => FunctionCategory::Core,
            'output'          => 'Provides direct patient care',
            'weight'          => 100,
        ]);
    }

    /** A catalog function graded on Efficiency alone. */
    private function gradedOnEfficiency(): JobFunction
    {
        $function = JobFunction::create([
            'category'                => FunctionCategory::Core,
            'title'                   => 'Complete DTR submitted',
            'accomplishment_template' => '{e}% submitted on time',
            'is_active'               => true,
        ]);

        $measure = FunctionMeasure::create([
            'job_function_id' => $function->id,
            'measure'         => RatingMeasure::Efficiency->value,
            'answer'          => MeasureAnswer::Number->value,
            'unit'            => '%',
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

    private function rate(IpcrItem $item, array $marks, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->owner)->put(
            route('ipcrs.items.update', [$this->ipcr, $item]),
            array_merge(['output' => $item->output, 'marks' => $marks], $extra),
        );
    }

    // -----------------------------------------------------------------
    // The owner sets the marks
    // -----------------------------------------------------------------

    public function test_the_owner_marks_their_own_line(): void
    {
        $item = $this->line();

        $this->rate($item, ['quality' => 5, 'efficiency' => 4, 'timeliness' => 3])
            ->assertSessionHasNoErrors();

        $item = $item->fresh();
        $this->assertSame('5.00', $item->quality_rating);
        $this->assertSame('4.00', $item->efficiency_rating);
        $this->assertSame('3.00', $item->timeliness_rating);
        $this->assertSame('4.000', $item->average_rating);
    }

    /** A measure that does not apply is left out, not marked zero. */
    public function test_a_measure_left_blank_stays_not_applicable(): void
    {
        $item = $this->line();

        $this->rate($item, ['quality' => 5, 'efficiency' => 4, 'timeliness' => '']);

        $item = $item->fresh();
        $this->assertNull($item->timeliness_rating);
        $this->assertSame('4.500', $item->average_rating);
    }

    public function test_a_mark_outside_one_to_five_is_refused(): void
    {
        $this->rate($this->line(), ['quality' => 7])->assertSessionHasErrors('marks.quality');
    }

    /**
     * The figure still decides a graded measure.
     *
     * Self-rating does not reopen what the rubric settled - a typed mark
     * beside a reported figure would contradict the sentence printed next to
     * it, whoever typed it.
     */
    public function test_a_mark_the_rubric_grades_cannot_be_typed_over(): void
    {
        $item = $this->line($this->gradedOnEfficiency());

        $this->rate($item, [], ['reported' => ['efficiency' => ['value' => 95]]]);
        $this->rate($item, ['efficiency' => 1, 'quality' => 5]);

        $item = $item->fresh();
        $this->assertSame('4.00', $item->efficiency_rating, 'The reported 95% is still a 4.');
        $this->assertSame('5.00', $item->quality_rating, 'What the rubric says nothing about is theirs to mark.');
    }

    public function test_nobody_else_can_mark_it(): void
    {
        $item = $this->line();

        $this->actingAs($this->sectionHead)
            ->put(route('ipcrs.items.update', [$this->ipcr, $item]), [
                'output' => $item->output, 'marks' => ['quality' => 1],
            ])
            ->assertForbidden();

        $this->assertNull($item->fresh()->quality_rating);
    }

    // -----------------------------------------------------------------
    // The approvers no longer type marks
    // -----------------------------------------------------------------

    public function test_the_rating_endpoint_is_gone(): void
    {
        $this->assertFalse(
            app('router')->has('ipcrs.ratings.update'),
            'Approving is not rating. There is nothing for an approver to save.'
        );
    }

    public function test_the_approver_sees_the_marks_but_no_boxes(): void
    {
        $item = $this->line();
        $this->rate($item, ['quality' => 5, 'efficiency' => 4, 'timeliness' => 3]);

        $this->ipcr->update([
            'status'               => IpcrStatus::Submitted,
            'submitted_at'         => now(),
            'assessor_employee_id' => $this->sectionHead->employee->id,
        ]);

        $html = $this->actingAs($this->sectionHead)
            ->get(route('ipcrs.show', $this->ipcr))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('name="ratings', $html);
        $this->assertStringNotContainsString('Save ratings', $html);
        $this->assertStringContainsString('Complete assessment', $html);
        $this->assertStringContainsString('Return for revision', $html);
    }

    // -----------------------------------------------------------------
    // Submitting
    // -----------------------------------------------------------------

    /** An approver must never receive a line they cannot mark and cannot fix. */
    public function test_an_unmarked_line_cannot_be_submitted(): void
    {
        // Accomplished but unmarked, so it is the rating guard that trips and
        // not the one about the sentence.
        $item = $this->line();
        $this->rate($item, [], ['actual_accomplishment' => 'Seen within 30 minutes every shift']);

        $this->actingAs($this->owner)
            ->post(route('ipcrs.submit', $this->ipcr))
            ->assertSessionHas('error', fn (string $m): bool => str_contains($m, 'no rating'));

        $this->assertSame(IpcrStatus::Draft, $this->ipcr->fresh()->status);
    }

    public function test_a_marked_ipcr_submits(): void
    {
        $item = $this->line();
        $this->rate($item, ['quality' => 5, 'efficiency' => 4, 'timeliness' => 3], [
            'actual_accomplishment' => 'Seen within 30 minutes every shift',
        ]);

        $this->actingAs($this->owner)
            ->post(route('ipcrs.submit', $this->ipcr))
            ->assertSessionHasNoErrors();

        $this->assertSame(IpcrStatus::Submitted, $this->ipcr->fresh()->status);
    }

    /**
     * Targets only is a commitment made before the work, so there is nothing
     * to rate and nothing to withhold it for.
     */
    public function test_a_targets_only_ipcr_needs_no_marks(): void
    {
        $this->ipcr->update(['mode' => IpcrMode::TargetsOnly]);
        $this->line();

        $this->actingAs($this->owner)
            ->post(route('ipcrs.submit', $this->ipcr))
            ->assertSessionHasNoErrors();

        $this->assertSame(IpcrStatus::Submitted, $this->ipcr->fresh()->status);
    }

    // -----------------------------------------------------------------
    // The form
    // -----------------------------------------------------------------

    public function test_the_editor_offers_a_mark_for_each_ungraded_measure(): void
    {
        $this->line();

        $html = $this->actingAs($this->owner)
            ->get(route('ipcrs.show', $this->ipcr))
            ->assertOk()
            ->getContent();

        foreach (RatingMeasure::cases() as $measure) {
            $this->assertStringContainsString('marks[' . $measure->value . ']', $html);
        }
    }

    /** A graded measure is shown, not asked for. */
    public function test_the_editor_does_not_ask_for_a_mark_the_rubric_grades(): void
    {
        $this->line($this->gradedOnEfficiency());

        $html = $this->actingAs($this->owner)
            ->get(route('ipcrs.show', $this->ipcr))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('marks[efficiency]', $html);
        $this->assertStringContainsString('marks[quality]', $html);
    }
}
