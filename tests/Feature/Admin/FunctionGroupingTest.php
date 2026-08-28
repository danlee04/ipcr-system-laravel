<?php

namespace Tests\Feature\Admin;

use App\Enums\FunctionCategory;
use App\Models\Employee;
use App\Models\JobFunction;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * How the catalog reads on the Functions screen.
 *
 * A function open to the whole hospital has a category like any other, but
 * that is not how anyone looks for it: they look for "the ones everybody
 * carries". So the list is grouped in four - common first, then core, support
 * and strategic - and the filter can ask for the common ones by name.
 *
 * "Everyone" was the old word for it in two places and "common" in a third.
 * One thing, one name.
 */
class FunctionGroupingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->admin = User::factory()->create();
        Employee::factory()->create(['user_id' => $this->admin->id]);
        $this->admin->assignRole('admin');
        $this->admin = $this->admin->fresh();
    }

    private function common(string $title, FunctionCategory $category = FunctionCategory::Core): JobFunction
    {
        return JobFunction::create([
            'category' => $category, 'title' => $title, 'is_active' => true,
        ]);
    }

    private function ofPosition(string $title, FunctionCategory $category): JobFunction
    {
        return JobFunction::create([
            'category'    => $category,
            'position_id' => Position::factory()->create()->id,
            'title'       => $title,
            'is_active'   => true,
        ]);
    }

    private function html(array $query = []): string
    {
        return $this->actingAs($this->admin)
            ->get(route('admin.functions.index', $query))
            ->assertOk()
            ->getContent();
    }

    // -----------------------------------------------------------------
    // The name
    // -----------------------------------------------------------------

    public function test_a_function_open_to_everyone_is_called_a_common_function(): void
    {
        $this->common('Observes the working hours');

        $html = $this->html();

        $this->assertStringContainsString('Common Function', $html);
        $this->assertStringNotContainsString('>Everyone<', $html);
    }

    public function test_the_category_filter_offers_common_functions(): void
    {
        $this->assertMatchesRegularExpression(
            '/<option value="common"[^>]*>\s*Common Function/',
            $this->html(),
        );
    }

    // -----------------------------------------------------------------
    // Filtering by it
    // -----------------------------------------------------------------

    public function test_filtering_by_common_leaves_out_the_work_of_a_post(): void
    {
        $this->common('Observes the working hours');
        $this->ofPosition('Provides direct patient care', FunctionCategory::Core);

        $html = $this->html(['category' => 'common']);

        $this->assertStringContainsString('Observes the working hours', $html);
        $this->assertStringNotContainsString('Provides direct patient care', $html);
    }

    /** A common function still has a category, and asking for that finds it. */
    public function test_filtering_by_a_category_still_finds_a_common_function(): void
    {
        $this->common('Observes the working hours', FunctionCategory::Support);

        $this->assertStringContainsString(
            'Observes the working hours',
            $this->html(['category' => 'support']),
        );
    }

    // -----------------------------------------------------------------
    // The four groups
    // -----------------------------------------------------------------

    public function test_the_groups_run_common_core_support_strategic(): void
    {
        $this->common('A common one');
        $this->ofPosition('A core one', FunctionCategory::Core);
        $this->ofPosition('A support one', FunctionCategory::Support);
        $this->ofPosition('A strategic one', FunctionCategory::Strategic);

        $this->assertSame(
            ['Common Function', 'Core Function', 'Support Function', 'Strategic Function'],
            $this->groupsOn($this->html()),
        );
    }

    /** Said once over the block, not repeated against every row. */
    public function test_a_group_is_named_once_however_many_rows_it_has(): void
    {
        $this->ofPosition('First', FunctionCategory::Core);
        $this->ofPosition('Second', FunctionCategory::Core);
        $this->ofPosition('Third', FunctionCategory::Core);

        $this->assertSame(['Core Function'], $this->groupsOn($this->html()));
    }

    /** A common function goes in the common group whatever its category. */
    public function test_a_common_function_is_not_listed_under_its_category(): void
    {
        $this->common('A common one', FunctionCategory::Strategic);

        $this->assertSame(['Common Function'], $this->groupsOn($this->html()));
    }

    // -----------------------------------------------------------------
    // The Category column
    // -----------------------------------------------------------------

    /**
     * A badge is a fixed little shape, not a sentence.
     *
     * Left-aligned it sat against the column rule with a wide gap after it,
     * and a column of them read as ragged. The heading goes with them, or the
     * two would disagree about where the column is.
     */
    public function test_the_category_badge_is_centred_in_its_column(): void
    {
        $this->common('Observes the working hours');

        $html = $this->html();

        $this->assertStringContainsString(
            'text-center',
            $this->classOf('#<th[^>]*class="([^"]*)"[^>]*>\s*Category\s*</th>#s', $html),
            'The Category heading is not centred.',
        );

        $this->assertStringContainsString(
            'text-center',
            $this->classOf(
                '#<td class="([^"]*)">\s*<span\s+class="inline-flex items-center rounded-full px-2\.5#s',
                $html,
            ),
            'The category badge cell is not centred.',
        );
    }

    /** The class attribute the pattern captures. */
    private function classOf(string $pattern, string $html): string
    {
        $this->assertSame(1, preg_match($pattern, $html, $match), 'Nothing matched ' . $pattern);

        return $match[1];
    }

    // -----------------------------------------------------------------
    // Paging
    // -----------------------------------------------------------------

    /** Five in each block, not five in the list. */
    public function test_each_block_holds_five_of_its_own(): void
    {
        $this->fill();

        $this->assertSame(
            ['Common Function' => 5, 'Core Function' => 5],
            $this->rowsPerGroup($this->html()),
        );
    }

    /**
     * They page separately, so turning one page does not move the others.
     *
     * The whole point of counting them apart: how much core work is written
     * down says nothing about how many lines everybody carries, and one page
     * number would have paged them together.
     */
    public function test_paging_one_block_leaves_the_others_where_they_were(): void
    {
        $this->fill();

        $this->assertSame(
            ['Common Function' => 5, 'Core Function' => 2],
            $this->rowsPerGroup($this->html(['core_page' => 2])),
        );
    }

    /** It sits over six columns, so the middle is where it belongs. */
    public function test_the_block_heading_is_centred_over_its_rows(): void
    {
        $this->common('Observes the working hours');

        $this->assertMatchesRegularExpression(
            '/<th scope="colgroup"[^>]*class="[^"]*text-center/s',
            $this->html(),
        );
    }

    /**
     * Every page link points back at this list, and nowhere else.
     *
     * This is the one thing live paging rests on: the handler compares a
     * clicked link's path against the list's own before it takes the click
     * off the browser. A link pointing anywhere else would be followed, and
     * the whole screen would reload.
     */
    public function test_every_page_link_points_back_at_the_list(): void
    {
        $this->fill();

        preg_match_all(
            '#<nav role="navigation" aria-label="Pagination Navigation".*?</nav>#s',
            $this->html(),
            $navs,
        );

        $this->assertNotEmpty($navs[0], 'No pager rendered, so nothing was checked.');

        $wanted = parse_url(route('admin.functions.index'), PHP_URL_PATH);

        foreach ($navs[0] as $nav) {
            preg_match_all('/<a\s+href="([^"]+)"/', $nav, $links);

            $this->assertNotEmpty($links[1]);

            foreach ($links[1] as $href) {
                $this->assertSame($wanted, parse_url($href, PHP_URL_PATH));
            }
        }
    }

    /** Seven in each of two blocks: enough for a second page in both. */
    private function fill(): void
    {
        foreach (range(1, 7) as $number) {
            $this->common("Common {$number}");
            $this->ofPosition("Core {$number}", FunctionCategory::Core);
        }
    }

    /**
     * How many rows sit under each heading.
     *
     * One Activate/Deactivate form per row, so counting those counts rows -
     * and the editors below the table carry an update form, never this one.
     *
     * @return array<string, int>
     */
    private function rowsPerGroup(string $html): array
    {
        $parts = preg_split(
            '#<th scope="colgroup"[^>]*>\s*([^<]+?)\s*<#s',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
        );

        $counts = [];

        for ($i = 1; $i < count($parts); $i += 2) {
            $counts[$parts[$i]] = preg_match_all(
                '#<form method="POST" action="[^"]+/active"#',
                $parts[$i + 1],
            );
        }

        return $counts;
    }

    /**
     * The group headings, in the order they appear.
     *
     * A row that heads a block of rows is a th with scope="colgroup" - real
     * markup that a screen reader announces, rather than a hook put there for
     * this test to find.
     *
     * @return list<string>
     */
    private function groupsOn(string $html): array
    {
        preg_match_all('#<th scope="colgroup"[^>]*>\s*([^<]+?)\s*<#s', $html, $matches);

        return $matches[1];
    }
}
