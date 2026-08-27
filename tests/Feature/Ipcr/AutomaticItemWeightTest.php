<?php

namespace Tests\Feature\Ipcr;

use App\Enums\FunctionCategory;
use App\Enums\IpcrStatus;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The weight on an IPCR line fills itself in.
 *
 * Weights have to total 100 in each category before an IPCR can be submitted.
 * Making the employee work that out by hand is arithmetic the system already
 * knows how to do - and getting it wrong is only discovered at the very last
 * step, when they press Submit.
 *
 * So a line added without a weight takes whatever is left in its category.
 * The first one takes 100, and each one after takes the remainder, which
 * means the total is correct at every point rather than only at the end.
 */
class AutomaticItemWeightTest extends TestCase
{
    use RefreshDatabase;

    private Employee $owner;

    private Ipcr $ipcr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = Employee::factory()->create(['user_id' => User::factory()->create()->id]);
        $this->ipcr = Ipcr::factory()->create([
            'employee_id' => $this->owner->id,
            'status'      => IpcrStatus::Draft,
        ]);
    }

    private function add(array $overrides = []): void
    {
        $this->actingAs($this->owner->user)
            ->post(route('ipcrs.items.store', $this->ipcr), $overrides + [
                'category' => FunctionCategory::Core->value,
                'output'   => 'Something worth doing',
            ])
            ->assertSessionHasNoErrors();
    }

    private function weights(FunctionCategory $category = FunctionCategory::Core): array
    {
        return $this->ipcr->items()
            ->where('category', $category->value)
            ->orderBy('sort_order')
            ->pluck('weight')
            ->map(fn ($w) => (float) $w)
            ->all();
    }

    // -----------------------------------------------------------------
    // Filling itself in
    // -----------------------------------------------------------------

    public function test_the_first_function_in_a_category_takes_all_of_it(): void
    {
        $this->add();

        $this->assertSame([100.0], $this->weights());
    }

    public function test_the_next_one_takes_what_is_left(): void
    {
        $this->add(['weight' => 60]);
        $this->add();

        $this->assertSame([60.0, 40.0], $this->weights());
    }

    /** Three lines, none of them weighted by hand, still total 100. */
    public function test_the_total_is_never_wrong(): void
    {
        $this->add(['weight' => 30]);
        $this->add(['weight' => 25]);
        $this->add();

        $this->assertSame(100.0, array_sum($this->weights()));
    }

    public function test_a_weight_typed_by_hand_still_wins(): void
    {
        $this->add(['weight' => 35]);

        $this->assertSame([35.0], $this->weights());
    }

    /** Nothing left to give: zero, not a negative number and not an overflow. */
    public function test_a_full_category_gives_the_next_line_nothing(): void
    {
        $this->add(['weight' => 100]);
        $this->add();

        $this->assertSame([100.0, 0.0], $this->weights());
    }

    /** Each category is counted on its own. */
    public function test_categories_do_not_borrow_from_each_other(): void
    {
        $this->add(['weight' => 100]);
        $this->add(['category' => FunctionCategory::Support->value]);

        $this->assertSame([100.0], $this->weights(FunctionCategory::Support));
    }

    public function test_a_blank_weight_is_treated_as_no_weight(): void
    {
        $this->add(['weight' => '']);

        $this->assertSame([100.0], $this->weights());
    }

    // -----------------------------------------------------------------
    // What it is for
    // -----------------------------------------------------------------

    /**
     * The point of all this: an IPCR built without touching a single weight
     * passes the submit guard, which used to be the last thing to fail and
     * the hardest to fix.
     */
    public function test_an_ipcr_built_without_typing_a_weight_can_be_submitted(): void
    {
        $this->add();
        $this->add(['category' => FunctionCategory::Support->value]);

        $this->assertSame([], $this->ipcr->fresh()->load('items')->categoriesWithBadWeightTotals());
    }

    // -----------------------------------------------------------------
    // The catalog no longer carries one
    // -----------------------------------------------------------------

    public function test_adding_from_the_catalog_sends_no_weight_of_its_own(): void
    {
        IpcrItem::factory()->create([
            'ipcr_id' => $this->ipcr->id, 'category' => FunctionCategory::Core, 'weight' => 70,
        ]);

        // What the catalog button posts now: no weight at all.
        $this->add(['job_function_id' => null]);

        $this->assertSame([70.0, 30.0], $this->weights());
    }
}
