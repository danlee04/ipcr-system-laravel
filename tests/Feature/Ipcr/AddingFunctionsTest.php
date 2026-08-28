<?php

namespace Tests\Feature\Ipcr;

use App\Enums\FunctionCategory;
use App\Enums\IpcrStatus;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrPeriod;
use App\Models\JobFunction;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Picking functions out of the catalog, all at once.
 *
 * Every function used to be its own little form with its own button, so an
 * employee with twelve of them clicked twelve times and waited for twelve page
 * loads to build one IPCR. They are ticked off a list now and added together.
 *
 * The ids are checked against the employee's own catalog rather than trusted.
 * A list of numbers in a form is exactly the sort of thing a crafted request
 * names something else in - and a function nobody's post reaches has no
 * business on their IPCR.
 */
class AddingFunctionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Ipcr $ipcr;

    private Position $position;

    protected function setUp(): void
    {
        parent::setUp();

        $this->position = Position::factory()->create();

        $user = User::factory()->create();
        $employee = Employee::factory()->create([
            'user_id' => $user->id, 'position_id' => $this->position->id,
        ]);

        $this->owner = $user->fresh();
        $this->ipcr = Ipcr::factory()->create([
            'employee_id'    => $employee->id,
            'ipcr_period_id' => IpcrPeriod::factory()->create(['status' => 'open'])->id,
            'status'         => IpcrStatus::Draft,
        ]);
    }

    private function reachable(string $title, FunctionCategory $category = FunctionCategory::Core): JobFunction
    {
        return JobFunction::create([
            'category'          => $category,
            'position_id'       => $this->position->id,
            'title'             => $title,
            'success_indicator' => "Indicator for {$title}",
            'is_active'         => true,
        ]);
    }

    /** Tied to somebody else's post, so it reaches this employee with nothing. */
    private function outOfReach(string $title): JobFunction
    {
        return JobFunction::create([
            'category'    => FunctionCategory::Core,
            'position_id' => Position::factory()->create()->id,
            'title'       => $title,
            'is_active'   => true,
        ]);
    }

    private function add(array $ids): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->owner)->post(
            route('ipcrs.items.catalog', $this->ipcr),
            ['job_function_ids' => $ids],
        );
    }

    // -----------------------------------------------------------------
    // Several at once
    // -----------------------------------------------------------------

    public function test_several_functions_are_added_in_one_go(): void
    {
        $first = $this->reachable('Provides direct patient care');
        $second = $this->reachable('Records vital signs');
        $third = $this->reachable('Attends to admissions');

        $this->add([$first->id, $second->id, $third->id])->assertSessionHasNoErrors();

        $this->assertSame(
            ['Provides direct patient care', 'Records vital signs', 'Attends to admissions'],
            $this->ipcr->items()->orderBy('sort_order')->pluck('output')->all()
        );
    }

    public function test_each_line_keeps_its_category_indicator_and_catalog_link(): void
    {
        $function = $this->reachable('Reviews the monthly report', FunctionCategory::Support);

        $this->add([$function->id]);

        $this->assertDatabaseHas('ipcr_items', [
            'ipcr_id'           => $this->ipcr->id,
            'job_function_id'   => $function->id,
            'category'          => 'support',
            'output'            => 'Reviews the monthly report',
            'success_indicator' => 'Indicator for Reviews the monthly report',
        ]);
    }

    /** The weights are settled once, after all of them are in. */
    public function test_the_weights_are_shared_across_everything_added(): void
    {
        $ids = collect(['One', 'Two', 'Three'])->map(fn (string $t): int => $this->reachable($t)->id)->all();

        $this->add($ids);

        $this->assertSame(
            ['33.33', '33.33', '33.34'],
            $this->ipcr->items()->orderBy('sort_order')->pluck('weight')->all()
        );
    }

    public function test_each_category_is_shared_on_its_own(): void
    {
        $core = $this->reachable('Core work');
        $supportOne = $this->reachable('Support one', FunctionCategory::Support);
        $supportTwo = $this->reachable('Support two', FunctionCategory::Support);

        $this->add([$core->id, $supportOne->id, $supportTwo->id]);

        $this->assertSame([
            'Core work'   => '100.00',
            'Support one' => '50.00',
            'Support two' => '50.00',
        ], $this->ipcr->items()->orderBy('sort_order')->pluck('weight', 'output')->all());
    }

    // -----------------------------------------------------------------
    // What is refused, and what is merely skipped
    // -----------------------------------------------------------------

    /** A second click is not an error - it is a second click. */
    public function test_a_function_already_on_the_ipcr_is_skipped(): void
    {
        $function = $this->reachable('Provides direct patient care');

        $this->add([$function->id]);
        $this->add([$function->id])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->ipcr->items()->count());
    }

    public function test_a_function_out_of_reach_is_never_added(): void
    {
        $mine = $this->reachable('Mine');
        $theirs = $this->outOfReach('Somebody elses');

        $this->add([$mine->id, $theirs->id]);

        $this->assertSame(['Mine'], $this->ipcr->items()->pluck('output')->all());
    }

    public function test_choosing_nothing_says_so(): void
    {
        $this->actingAs($this->owner)
            ->post(route('ipcrs.items.catalog', $this->ipcr), [])
            ->assertSessionHasErrors('job_function_ids');
    }

    public function test_a_submitted_ipcr_takes_no_more(): void
    {
        $function = $this->reachable('Too late');
        $this->ipcr->update(['status' => IpcrStatus::Submitted, 'submitted_at' => now()]);

        $this->add([$function->id])->assertForbidden();

        $this->assertSame(0, $this->ipcr->items()->count());
    }

    public function test_somebody_else_cannot_add_to_it(): void
    {
        $function = $this->reachable('Not yours');

        $stranger = User::factory()->create();
        Employee::factory()->create(['user_id' => $stranger->id]);

        $this->actingAs($stranger->fresh())
            ->post(route('ipcrs.items.catalog', $this->ipcr), ['job_function_ids' => [$function->id]])
            ->assertForbidden();
    }

    // -----------------------------------------------------------------
    // The picker
    // -----------------------------------------------------------------

    public function test_the_page_offers_one_tick_box_per_function(): void
    {
        $function = $this->reachable('Provides direct patient care');

        $this->actingAs($this->owner)
            ->get(route('ipcrs.show', $this->ipcr))
            ->assertOk()
            ->assertSee('name="job_function_ids[]"', false)
            ->assertSee('value="' . $function->id . '"', false);
    }

    /** One button for the lot, rather than one form per function. */
    public function test_there_is_a_single_add_button(): void
    {
        $this->reachable('One');
        $this->reachable('Two');

        $html = $this->actingAs($this->owner)
            ->get(route('ipcrs.show', $this->ipcr))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            substr_count($html, 'action="' . route('ipcrs.items.catalog', $this->ipcr) . '"')
        );
    }

    /** Already on the IPCR: shown as such, and not offered a second time. */
    public function test_a_function_already_added_is_marked(): void
    {
        $function = $this->reachable('Provides direct patient care');
        $this->add([$function->id]);

        $this->actingAs($this->owner)
            ->get(route('ipcrs.show', $this->ipcr))
            ->assertOk()
            ->assertSee('Added');
    }
}
