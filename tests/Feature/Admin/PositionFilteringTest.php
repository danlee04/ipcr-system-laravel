<?php

namespace Tests\Feature\Admin;

use App\Models\Designation;
use App\Models\Division;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The filters on the Positions screen.
 *
 * The controller has narrowed positions by division and section for a while;
 * the page never offered the selects, so nothing ever sent them. A filter
 * nobody can reach is the same as no filter.
 *
 * The two tabs do not take the same filters. A position sits in a section and
 * so in a division; a designation sits nowhere - offering it a division would
 * be asking a question with no answer.
 */
class PositionFilteringTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function placement(): array
    {
        $division = Division::factory()->create(['name' => 'Administrative Division']);
        $section = Section::factory()->create(['division_id' => $division->id, 'name' => 'HRD Section']);

        return [$division, $section];
    }

    private function html(array $query = []): string
    {
        return $this->actingAs($this->admin())
            ->get(route('admin.positions.index', $query))
            ->assertOk()
            ->getContent();
    }

    /** @return array<int, string> the titles listed for the given query */
    private function listed(array $query, string $key = 'positions'): array
    {
        return $this->actingAs($this->admin())
            ->get(route('admin.positions.index', $query))
            ->assertOk()
            ->viewData($key)
            ->pluck('title')
            ->all();
    }

    // -----------------------------------------------------------------
    // The selects are on the page at all
    // -----------------------------------------------------------------

    public function test_the_positions_tab_offers_division_and_section(): void
    {
        $this->placement();

        $html = $this->html();

        $this->assertStringContainsString('name="division"', $html);
        $this->assertStringContainsString('name="section"', $html);
    }

    /** Division narrows Section, so the pair can never describe no rows. */
    public function test_the_section_options_declare_their_division(): void
    {
        [$division, $section] = $this->placement();

        $html = $this->html();

        preg_match('/<select name="section".*?<\/select>/s', $html, $matches);

        $this->assertNotEmpty($matches, 'The section filter should be on the page.');
        $this->assertStringContainsString('data-division="' . $division->id . '"', $matches[0]);
    }

    /**
     * A designation belongs to no section and no division. Offering those
     * filters on that tab would invite a search that can only come back empty.
     */
    public function test_the_designations_tab_offers_neither(): void
    {
        $this->placement();

        $html = $this->html(['tab' => 'designations']);

        $this->assertStringNotContainsString('name="division"', $html);
        $this->assertStringNotContainsString('name="section"', $html);
    }

    public function test_both_tabs_offer_a_status_filter(): void
    {
        $this->assertStringContainsString('name="status"', $this->html());
        $this->assertStringContainsString('name="status"', $this->html(['tab' => 'designations']));
    }

    // -----------------------------------------------------------------
    // They narrow what is listed
    // -----------------------------------------------------------------

    public function test_filtering_positions_by_section_narrows_the_list(): void
    {
        [$division, $section] = $this->placement();

        Position::factory()->create(['section_id' => $section->id, 'title' => 'HR Officer II']);
        Position::factory()->create(['title' => 'Nurse II']);

        $this->assertSame(['HR Officer II'], $this->listed(['section' => $section->id]));
    }

    public function test_filtering_positions_by_division_narrows_the_list(): void
    {
        [$division, $section] = $this->placement();

        Position::factory()->create(['section_id' => $section->id, 'title' => 'HR Officer II']);
        Position::factory()->create(['title' => 'Nurse II']);

        $this->assertSame(['HR Officer II'], $this->listed(['division' => $division->id]));
    }

    public function test_positions_can_be_narrowed_to_the_inactive_ones(): void
    {
        Position::factory()->create(['title' => 'Still in use', 'is_active' => true]);
        Position::factory()->create(['title' => 'Abolished post', 'is_active' => false]);

        $this->assertSame(['Abolished post'], $this->listed(['status' => 'inactive']));
        $this->assertSame(['Still in use'], $this->listed(['status' => 'active']));
    }

    public function test_designations_can_be_narrowed_to_the_inactive_ones(): void
    {
        Designation::factory()->create(['title' => 'OIC - Budget Officer', 'is_active' => true]);
        Designation::factory()->create(['title' => 'OIC - Retired Post', 'is_active' => false]);

        $listed = $this->listed(['tab' => 'designations', 'status' => 'inactive'], 'designations');

        $this->assertSame(['OIC - Retired Post'], $listed);
    }

    /** An unknown status is ignored rather than emptying the list. */
    public function test_a_meaningless_status_is_ignored(): void
    {
        Position::factory()->create(['title' => 'Nurse II']);

        $this->assertSame(['Nurse II'], $this->listed(['status' => 'banana']));
    }

    // -----------------------------------------------------------------
    // What filtering must not break
    // -----------------------------------------------------------------

    /**
     * The tab lives in the query string. A filter that dropped it would send
     * the administrator back to Positions the moment they searched.
     */
    public function test_filtering_on_the_designations_tab_stays_on_that_tab(): void
    {
        $html = $this->html(['tab' => 'designations']);

        preg_match('/<form method="GET".*?<\/form>/s', $html, $matches);

        $this->assertNotEmpty($matches);
        $this->assertStringContainsString('name="tab" value="designations"', $matches[0]);
    }

    /** The tab counts describe the whole set, not the filtered page. */
    public function test_the_tab_counts_ignore_the_filter(): void
    {
        [$division, $section] = $this->placement();

        Position::factory()->create(['section_id' => $section->id]);
        Position::factory()->count(2)->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.positions.index', ['section' => $section->id]))
            ->assertOk();

        $this->assertCount(1, $response->viewData('positions'));
        $this->assertSame(3, $response->viewData('positionCount'));
    }
}
