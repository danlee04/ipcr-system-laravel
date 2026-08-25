<?php

namespace Tests\Feature\Ipcr;

use App\Enums\FunctionCategory;
use App\Enums\IpcrMode;
use App\Enums\IpcrStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Within each category the item weights must total 100%.
 *
 * The rating maths tolerates any total - it takes a weighted mean - but the
 * CSC form does not, and an employee whose core functions add up to 150% has
 * made a mistake nobody was telling them about.
 *
 * The check runs at submission, not when a line is added: the first function
 * on a fresh IPCR would otherwise always be rejected.
 */
class ItemWeightTest extends TestCase
{
    use RefreshDatabase;

    /** An employee whose approval chain fully resolves, so submit can succeed. */
    private function employeeUserWithChain(): User
    {
        $division = Division::factory()->create();
        $section = Section::factory()->create(['division_id' => $division->id]);

        $sectionHead = Employee::factory()->create(['section_id' => $section->id]);
        $divisionHead = Employee::factory()->create(['division_id' => $division->id]);
        $section->update(['section_head_employee_id' => $sectionHead->id]);
        $division->update(['division_head_employee_id' => $divisionHead->id]);

        $user = User::factory()->create();
        Employee::factory()->create([
            'user_id'     => $user->id,
            'section_id'  => $section->id,
            'division_id' => $division->id,
        ]);

        return $user->fresh();
    }

    /**
     * Targets only, deliberately: the accomplishment guard runs before the
     * weight guard, and these tests are about weights alone.
     */
    private function draftFor(User $user): Ipcr
    {
        return Ipcr::factory()->create([
            'employee_id' => $user->employee->id,
            'status'      => IpcrStatus::Draft,
            'mode'        => IpcrMode::TargetsOnly,
        ]);
    }

    private function item(Ipcr $ipcr, FunctionCategory $category, ?float $weight): IpcrItem
    {
        return IpcrItem::factory()->create([
            'ipcr_id' => $ipcr->id, 'category' => $category, 'weight' => $weight,
        ]);
    }

    // -----------------------------------------------------------------
    // The model's own accounting
    // -----------------------------------------------------------------

    public function test_it_totals_the_weights_of_each_category(): void
    {
        $ipcr = $this->draftFor($this->employeeUserWithChain());
        $this->item($ipcr, FunctionCategory::Core, 60);
        $this->item($ipcr, FunctionCategory::Core, 40);
        $this->item($ipcr, FunctionCategory::Support, 100);

        $totals = $ipcr->fresh()->weightTotalsByCategory();

        $this->assertSame(100.0, $totals['core']);
        $this->assertSame(100.0, $totals['support']);
        $this->assertArrayNotHasKey('strategic', $totals, 'Only categories with items are counted.');
    }

    public function test_a_category_that_does_not_total_one_hundred_is_reported(): void
    {
        $ipcr = $this->draftFor($this->employeeUserWithChain());
        $this->item($ipcr, FunctionCategory::Core, 50);
        $this->item($ipcr, FunctionCategory::Core, 50);
        $this->item($ipcr, FunctionCategory::Support, 70);

        $offenders = $ipcr->fresh()->categoriesWithBadWeightTotals();

        $this->assertSame(['support' => 70.0], $offenders);
    }

    public function test_an_item_with_no_weight_counts_as_zero(): void
    {
        $ipcr = $this->draftFor($this->employeeUserWithChain());
        $this->item($ipcr, FunctionCategory::Core, 100);
        $this->item($ipcr, FunctionCategory::Core, null);

        $this->assertSame(['core' => 100.0], $ipcr->fresh()->weightTotalsByCategory());
        $this->assertSame([], $ipcr->fresh()->categoriesWithBadWeightTotals());
    }

    // -----------------------------------------------------------------
    // The submit guard
    // -----------------------------------------------------------------

    public function test_submitting_is_blocked_when_a_category_does_not_total_one_hundred(): void
    {
        $user = $this->employeeUserWithChain();
        $ipcr = $this->draftFor($user);
        $this->item($ipcr, FunctionCategory::Core, 60);

        $this->actingAs($user)->post(route('ipcrs.submit', $ipcr));

        $this->assertSame(IpcrStatus::Draft, $ipcr->fresh()->status);
        $this->assertStringContainsString('Core Function', (string) session('error'));
        $this->assertStringContainsString('60', (string) session('error'));
    }

    public function test_the_error_names_every_offending_category(): void
    {
        $user = $this->employeeUserWithChain();
        $ipcr = $this->draftFor($user);
        $this->item($ipcr, FunctionCategory::Core, 90);
        $this->item($ipcr, FunctionCategory::Support, 130);

        $this->actingAs($user)->post(route('ipcrs.submit', $ipcr));

        $error = (string) session('error');
        $this->assertStringContainsString('Core Function', $error);
        $this->assertStringContainsString('Support Function', $error);
    }

    public function test_submitting_succeeds_when_every_category_totals_one_hundred(): void
    {
        $user = $this->employeeUserWithChain();
        $ipcr = $this->draftFor($user);
        $this->item($ipcr, FunctionCategory::Core, 70);
        $this->item($ipcr, FunctionCategory::Core, 30);
        $this->item($ipcr, FunctionCategory::Support, 100);

        $this->actingAs($user)->post(route('ipcrs.submit', $ipcr));

        $this->assertSame(IpcrStatus::Submitted, $ipcr->fresh()->status);
    }

    /** Rounding must not make a legitimate 33.33 + 33.33 + 33.34 fail. */
    public function test_thirds_that_add_up_are_accepted(): void
    {
        $user = $this->employeeUserWithChain();
        $ipcr = $this->draftFor($user);
        $this->item($ipcr, FunctionCategory::Core, 33.33);
        $this->item($ipcr, FunctionCategory::Core, 33.33);
        $this->item($ipcr, FunctionCategory::Core, 33.34);

        $this->actingAs($user)->post(route('ipcrs.submit', $ipcr));

        $this->assertSame(IpcrStatus::Submitted, $ipcr->fresh()->status);
    }

    // -----------------------------------------------------------------
    // Caught while adding, not only at submission
    // -----------------------------------------------------------------

    public function test_adding_a_function_that_would_exceed_one_hundred_is_refused(): void
    {
        $user = $this->employeeUserWithChain();
        $ipcr = $this->draftFor($user);
        $this->item($ipcr, FunctionCategory::Core, 60);

        $this->actingAs($user)->post(route('ipcrs.items.store', $ipcr), [
            'category' => FunctionCategory::Core->value,
            'output'   => 'One more thing',
            'weight'   => 50,
        ]);

        $this->assertSame(1, $ipcr->items()->count(), 'The line must not have been added.');
        $this->assertStringContainsString('40', (string) session('error'), 'The message should say how much room is left.');
    }

    public function test_adding_a_function_that_fits_exactly_is_allowed(): void
    {
        $user = $this->employeeUserWithChain();
        $ipcr = $this->draftFor($user);
        $this->item($ipcr, FunctionCategory::Core, 60);

        $this->actingAs($user)->post(route('ipcrs.items.store', $ipcr), [
            'category' => FunctionCategory::Core->value,
            'output'   => 'The remainder',
            'weight'   => 40,
        ]);

        $this->assertSame(2, $ipcr->items()->count());
        $this->assertSame([], $ipcr->fresh()->load('items')->categoriesWithBadWeightTotals());
    }

    public function test_a_full_category_does_not_block_a_different_one(): void
    {
        $user = $this->employeeUserWithChain();
        $ipcr = $this->draftFor($user);
        $this->item($ipcr, FunctionCategory::Core, 100);

        $this->actingAs($user)->post(route('ipcrs.items.store', $ipcr), [
            'category' => FunctionCategory::Support->value,
            'output'   => 'A support function',
            'weight'   => 100,
        ]);

        $this->assertSame(2, $ipcr->items()->count());
    }

    public function test_raising_an_existing_weight_beyond_the_category_total_is_refused(): void
    {
        $user = $this->employeeUserWithChain();
        $ipcr = $this->draftFor($user);
        $first = $this->item($ipcr, FunctionCategory::Core, 60);
        $this->item($ipcr, FunctionCategory::Core, 40);

        $this->actingAs($user)->put(route('ipcrs.items.update', [$ipcr, $first]), [
            'output' => $first->output,
            'weight' => 90,
        ]);

        $this->assertSame('60.00', $first->fresh()->weight);
        $this->assertStringContainsString('100%', (string) session('error'));
    }

    // -----------------------------------------------------------------
    // What the employee sees while building the IPCR
    // -----------------------------------------------------------------

    public function test_the_page_shows_the_running_total_for_each_category(): void
    {
        $user = $this->employeeUserWithChain();
        $ipcr = $this->draftFor($user);
        $this->item($ipcr, FunctionCategory::Core, 60);

        $this->actingAs($user)
            ->get(route('ipcrs.show', $ipcr))
            ->assertOk()
            ->assertSee('Core Function')
            ->assertSee('60 of 100%');
    }

    public function test_a_balanced_category_is_not_flagged_on_the_page(): void
    {
        $user = $this->employeeUserWithChain();
        $ipcr = $this->draftFor($user);
        $this->item($ipcr, FunctionCategory::Core, 100);

        $this->actingAs($user)
            ->get(route('ipcrs.show', $ipcr))
            ->assertOk()
            ->assertDontSee('60 of 100%');
    }

    // -----------------------------------------------------------------
    // The split actually used is kept on the record
    // -----------------------------------------------------------------

    public function test_the_category_split_is_stored_on_the_ipcr_when_it_is_rated(): void
    {
        $ipcr = Ipcr::factory()->create(['status' => IpcrStatus::Submitted]);

        IpcrItem::factory()->create([
            'ipcr_id' => $ipcr->id, 'category' => FunctionCategory::Core, 'weight' => 100,
            'quality_rating' => 5, 'efficiency_rating' => 5, 'timeliness_rating' => 5,
        ]);
        IpcrItem::factory()->create([
            'ipcr_id' => $ipcr->id, 'category' => FunctionCategory::Support, 'weight' => 100,
            'quality_rating' => 3, 'efficiency_rating' => 3, 'timeliness_rating' => 3,
        ]);

        $breakdown = app(\App\Services\RatingCalculator::class)->for($ipcr->fresh('items'));
        $ipcr->update($breakdown->toIpcrColumns());

        $ipcr->refresh();
        $this->assertSame('80.00', $ipcr->core_weight);
        $this->assertSame('20.00', $ipcr->support_weight);

        // The columns default to 30/50/20 in the migration - a split that
        // predates the rule. A category with no items must be written down as
        // zero, not left carrying that default.
        $this->assertSame('0.00', $ipcr->strategic_weight);
    }
}
