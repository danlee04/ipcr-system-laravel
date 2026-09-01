<?php

namespace Tests\Feature\Ipcr;

use App\Enums\FunctionCategory;
use App\Enums\IpcrStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrPeriod;
use App\Models\JobFunction;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Narrowing down to one other post before its work is shown.
 *
 * A hospital has hundreds of posts. Listing every one of them and all their
 * functions the moment the fold opens is the wall of text the fold was there
 * to prevent, so the block asks first: which division, which section, which
 * post. Only then does anything appear to tick.
 *
 * The three selects carry no name. They narrow what is on screen and nothing
 * else - a named control inside this form would be posted along with the
 * chosen ids and mean nothing at the other end.
 */
class BorrowedFunctionFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Ipcr $ipcr;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $employee = Employee::factory()->create([
            'user_id' => $user->id, 'position_id' => Position::factory()->create()->id,
        ]);

        $this->owner = $user->fresh();
        $this->ipcr = Ipcr::factory()->create([
            'employee_id'    => $employee->id,
            'ipcr_period_id' => IpcrPeriod::factory()->create(['status' => 'open'])->id,
            'status'         => IpcrStatus::Draft,
        ]);
    }

    /** A post in its own section and division, with one function on it. */
    private function otherPost(string $division, string $section, string $title): Position
    {
        $position = Position::factory()->create([
            'title'      => $title,
            'section_id' => Section::factory()->create([
                'name'        => $section,
                'division_id' => Division::factory()->create(['name' => $division])->id,
            ])->id,
        ]);

        JobFunction::create([
            'category'    => FunctionCategory::Core,
            'position_id' => $position->id,
            'title'       => "Work of the {$title}",
            'is_active'   => true,
        ]);

        return $position;
    }

    private function picker(): string
    {
        $html = $this->actingAs($this->owner)
            ->get(route('ipcrs.show', $this->ipcr))
            ->assertOk()
            ->getContent();

        $start = strpos($html, 'From another position');
        $end = strpos($html, 'Functions &amp; Outputs');

        $this->assertNotFalse($start, 'The other-positions block is missing.');

        return substr($html, $start, $end - $start);
    }

    // -----------------------------------------------------------------
    // The three selects
    // -----------------------------------------------------------------

    public function test_the_block_asks_for_a_division_a_section_and_a_post(): void
    {
        $this->otherPost('Medical Services', 'Nursing', 'Nurse III');

        $block = $this->picker();

        foreach (['division', 'section', 'position'] as $field) {
            $this->assertStringContainsString(
                'x-model="' . $field . '"',
                $block,
                "No {$field} filter.",
            );
        }
    }

    /**
     * None of them is posted. The form carries a list of function ids and
     * nothing else; a named select here would ride along and mean nothing at
     * the other end.
     */
    public function test_the_filters_are_not_part_of_what_is_submitted(): void
    {
        $this->otherPost('Medical Services', 'Nursing', 'Nurse III');

        $this->assertDoesNotMatchRegularExpression(
            '/<select[^>]*\sname=/',
            $this->picker(),
            'A filter would be submitted with the chosen functions.',
        );
    }

    /** Only posts with something left to offer: a dead end is not a choice. */
    public function test_a_post_with_nothing_on_it_is_not_offered(): void
    {
        $this->otherPost('Medical Services', 'Nursing', 'Nurse III');
        Position::factory()->create(['title' => 'Empty Post']);

        $block = $this->picker();

        $this->assertStringContainsString('Nurse III', $block);
        $this->assertStringNotContainsString('Empty Post', $block);
    }

    // -----------------------------------------------------------------
    // Nothing until a post is chosen
    // -----------------------------------------------------------------

    public function test_the_functions_wait_for_a_post_to_be_named(): void
    {
        $position = $this->otherPost('Medical Services', 'Nursing', 'Nurse III');

        $this->assertStringContainsString(
            'x-show="position === \'' . $position->id . '\'"',
            $this->picker(),
            'The functions are not held back until their post is chosen.',
        );
    }

    /**
     * A tick that scrolled out of sight is still a tick.
     *
     * Hidden checkboxes submit; that is the whole reason this is worth a test.
     * Changing a filter has to clear what it hides, or an employee would add
     * a function they cannot see and did not mean to choose.
     */
    public function test_changing_a_filter_clears_what_it_hides(): void
    {
        $this->otherPost('Medical Services', 'Nursing', 'Nurse III');
        $this->otherPost('Finance', 'Budget', 'Budget Officer II');

        $block = $this->picker();

        // Every select clears what it hides, and every group of checkboxes
        // says which post it belongs to - that pairing is what prune() walks.
        // Up to the first option, because the change handler holds an arrow
        // function and a naive match for the closing angle stops inside it.
        preg_match_all('#<select\b(.*?)<option#s', $block, $selects);

        $this->assertCount(3, $selects[1]);

        foreach ($selects[1] as $select) {
            $this->assertStringContainsString('prune()', $select);
        }

        $this->assertSame(2, substr_count($block, 'data-post="'));
    }
}
