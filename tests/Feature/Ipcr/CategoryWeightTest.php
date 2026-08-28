<?php

namespace Tests\Feature\Ipcr;

use App\Enums\FunctionCategory;
use App\Enums\IpcrStatus;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\IpcrPeriod;
use App\Models\User;
use App\Services\RatingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What each category is worth in the final rating.
 *
 * Two different weights live on an IPCR and it is worth keeping them apart.
 * Within a category the lines share its hundred equally - see
 * AutomaticItemWeightTest. Between categories the split is the CSC rule:
 *
 *   core and support only  -> Core 80, Support 20
 *   with strategic         -> Strategic 40, Core 50, Support 10
 *
 * Neither is typed by anyone. The second follows from what the employee
 * actually holds, so adding one strategic function changes the whole sheet.
 */
class CategoryWeightTest extends TestCase
{
    use RefreshDatabase;

    private function rated(Ipcr $ipcr, FunctionCategory $category, float $mark): IpcrItem
    {
        return IpcrItem::factory()->create([
            'ipcr_id'  => $ipcr->id,
            'category' => $category,
            'weight'   => 100,
            'quality_rating' => $mark, 'efficiency_rating' => $mark, 'timeliness_rating' => $mark,
        ]);
    }

    // -----------------------------------------------------------------
    // The split follows from what is there
    // -----------------------------------------------------------------

    public function test_core_and_support_alone_are_eighty_and_twenty(): void
    {
        $split = app(RatingCalculator::class)->weightsFor(['core', 'support']);

        $this->assertSame(['core' => 80.0, 'support' => 20.0], $split);
    }

    public function test_one_strategic_function_changes_the_whole_split(): void
    {
        $split = app(RatingCalculator::class)->weightsFor(['strategic', 'core', 'support']);

        $this->assertSame(['strategic' => 40.0, 'core' => 50.0, 'support' => 10.0], $split);
    }

    /** A missing category gives its share to the ones that are there. */
    public function test_core_alone_carries_everything(): void
    {
        $this->assertSame(['core' => 100.0], app(RatingCalculator::class)->weightsFor(['core']));
    }

    // -----------------------------------------------------------------
    // Written down on the record
    // -----------------------------------------------------------------

    public function test_the_split_actually_used_is_kept_on_the_ipcr(): void
    {
        $ipcr = Ipcr::factory()->create(['status' => IpcrStatus::Submitted]);

        $this->rated($ipcr, FunctionCategory::Core, 5);
        $this->rated($ipcr, FunctionCategory::Support, 3);

        $breakdown = app(RatingCalculator::class)->for($ipcr->fresh('items'));
        $ipcr->update($breakdown->toIpcrColumns());

        $ipcr->refresh();
        $this->assertSame('80.00', $ipcr->core_weight);
        $this->assertSame('20.00', $ipcr->support_weight);

        // The columns default to 30/50/20 in the migration - a split that
        // predates the rule. A category with no items must be written down as
        // zero, not left carrying that default.
        $this->assertSame('0.00', $ipcr->strategic_weight);
    }

    // -----------------------------------------------------------------
    // Shown to the employee
    // -----------------------------------------------------------------

    /**
     * The header used to count the item weights up to 100, which can no longer
     * be anything else. This is the number that still varies, and the only one
     * the employee can move - by adding or removing a function.
     */
    public function test_the_ipcr_page_shows_what_each_category_is_worth(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $ipcr = Ipcr::factory()->create([
            'employee_id'    => $employee->id,
            'ipcr_period_id' => IpcrPeriod::factory()->create(['status' => 'open'])->id,
            'status'         => IpcrStatus::Draft,
        ]);

        $this->rated($ipcr, FunctionCategory::Core, 5);
        $this->rated($ipcr, FunctionCategory::Support, 3);

        $html = $this->actingAs($user->fresh())
            ->get(route('ipcrs.show', $ipcr))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Core Function</span>', $html);
        $this->assertStringContainsString('80%', $html);
        $this->assertStringContainsString('20%', $html);

        $this->assertStringNotContainsString('of 100%', $html, 'The old running total is gone.');
    }
}
