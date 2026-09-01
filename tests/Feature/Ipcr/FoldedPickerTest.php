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
 * The picker opens shut.
 *
 * A hospital's catalog runs to dozens of lines per post, and printing all of
 * them above the sheet buried the sheet. Every category is a fold now - a
 * heading with a count, and nothing under it until it is asked for - so the
 * page opens as a short list of decisions rather than a wall of tick boxes.
 *
 * Both columns fold, and both fold the same way: three categories, in the
 * order the IPCR is read in. The common lines are not a fourth kind of work;
 * they are the same three kinds, open to everybody.
 */
class FoldedPickerTest extends TestCase
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

    private function mine(string $title, FunctionCategory $category): JobFunction
    {
        return JobFunction::create([
            'category'    => $category,
            'position_id' => $this->position->id,
            'title'       => $title,
            'is_active'   => true,
        ]);
    }

    private function common(string $title, FunctionCategory $category): JobFunction
    {
        return JobFunction::create([
            'category' => $category, 'title' => $title, 'is_active' => true,
        ]);
    }

    /** Just the picker card, so the sheet below it is not mistaken for it. */
    private function picker(): string
    {
        $html = $this->actingAs($this->owner)
            ->get(route('ipcrs.show', $this->ipcr))
            ->assertOk()
            ->getContent();

        $start = strpos($html, 'Add a Function');
        $end = strpos($html, 'Functions &amp; Outputs');

        $this->assertNotFalse($start, 'No picker on the page.');
        $this->assertNotFalse($end, 'No sheet on the page.');

        return substr($html, $start, $end - $start);
    }

    /** The summaries in the picker, in the order they appear. */
    private function folds(): array
    {
        preg_match_all(
            '#<span class="font-medium text-gray-800">\s*([^<]+?)\s*</span>#s',
            $this->picker(),
            $matches,
        );

        return $matches[1];
    }

    // -----------------------------------------------------------------
    // Shut until asked
    // -----------------------------------------------------------------

    public function test_no_category_is_open_to_begin_with(): void
    {
        $this->mine('Provides direct patient care', FunctionCategory::Core);
        $this->common('Observes the working hours', FunctionCategory::Support);

        $this->assertDoesNotMatchRegularExpression(
            '/<details[^>]*\sopen[\s>]/',
            $this->picker(),
            'A category is open before anyone clicked it.',
        );
    }

    public function test_a_function_is_still_there_to_be_ticked(): void
    {
        $function = $this->mine('Provides direct patient care', FunctionCategory::Core);

        $this->assertStringContainsString(
            'value="' . $function->id . '"',
            $this->picker(),
        );
    }

    // -----------------------------------------------------------------
    // Both columns, the same three categories
    // -----------------------------------------------------------------

    public function test_the_common_column_is_folded_by_category_too(): void
    {
        $this->common('A common core one', FunctionCategory::Core);
        $this->common('A common support one', FunctionCategory::Support);

        $this->assertSame(
            ['Core Function', 'Support Function'],
            $this->folds(),
        );
    }

    /** Core, then support, then strategic - wherever the sheet appears. */
    public function test_the_categories_run_in_the_order_the_ipcr_is_read_in(): void
    {
        $this->common('A common strategic one', FunctionCategory::Strategic);
        $this->common('A common support one', FunctionCategory::Support);
        $this->common('A common core one', FunctionCategory::Core);

        $this->assertSame(
            ['Core Function', 'Support Function', 'Strategic Function'],
            $this->folds(),
        );
    }

    /** A category with nothing in it is not a fold with nothing behind it. */
    public function test_an_empty_category_is_not_offered(): void
    {
        $this->common('A common core one', FunctionCategory::Core);

        $this->assertSame(['Core Function'], $this->folds());
    }

    public function test_each_fold_says_how_many_are_behind_it(): void
    {
        $this->mine('One', FunctionCategory::Core);
        $this->mine('Two', FunctionCategory::Core);
        $this->mine('Three', FunctionCategory::Core);

        $this->assertMatchesRegularExpression(
            '#Core Function\s*</span>\s*<span[^>]*>\s*3\s*</span>#s',
            $this->picker(),
        );
    }
}
