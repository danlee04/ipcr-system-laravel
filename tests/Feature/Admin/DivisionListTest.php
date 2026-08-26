<?php

namespace Tests\Feature\Admin;

use App\Models\Division;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Filtering and paging the Divisions screen.
 *
 * The page is a tree, not a flat list: every division brings its sections
 * with it. So it is the divisions that are paged - a section can never be
 * separated from the division it sits in, because that is the only place it
 * exists.
 */
class DivisionListTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    // -----------------------------------------------------------------
    // Paging
    // -----------------------------------------------------------------

    /**
     * Asserted on the view's data rather than the rendered page.
     *
     * Every division name appears on the page regardless - the filter
     * dropdowns list them all, and so do the division selects inside the
     * section forms. assertDontSee would be measuring those, not the table.
     */
    private function listed(array $query = []): \Illuminate\Support\Collection
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.divisions.index', $query))
            ->assertOk();

        return collect($response->viewData('divisions')->items());
    }

    public function test_the_list_is_paged(): void
    {
        Division::factory()->count(12)->sequence(
            fn ($sequence) => ['name' => 'Division ' . str_pad((string) ($sequence->index + 1), 2, '0', STR_PAD_LEFT)]
        )->create();

        $first = $this->listed();
        $this->assertCount(10, $first);
        $this->assertSame('Division 01', $first->first()->name);

        $second = $this->listed(['page' => 2]);
        $this->assertCount(2, $second);
        $this->assertSame('Division 11', $second->first()->name);
    }

    public function test_a_division_keeps_its_sections_on_whichever_page_it_lands(): void
    {
        Division::factory()->count(10)->create();

        $division = Division::factory()->create(['name' => 'Zulu Division']);
        Section::factory()->create(['division_id' => $division->id, 'name' => 'Zulu Section']);

        $this->actingAs($this->admin())
            ->get(route('admin.divisions.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Zulu Division')
            ->assertSee('Zulu Section');
    }

    // -----------------------------------------------------------------
    // Filtering
    // -----------------------------------------------------------------

    public function test_filtering_by_division_shows_only_that_division(): void
    {
        $wanted = Division::factory()->create(['name' => 'Administrative Division']);
        Division::factory()->create(['name' => 'Medical Services Division']);

        $listed = $this->listed(['division' => $wanted->id]);

        $this->assertCount(1, $listed);
        $this->assertSame('Administrative Division', $listed->first()->name);
    }

    /**
     * A section filter narrows both levels: the division it sits in, and
     * inside that division, only the section asked for. Showing its siblings
     * would answer a question nobody asked.
     */
    public function test_filtering_by_section_narrows_to_that_section_inside_its_division(): void
    {
        $division = Division::factory()->create(['name' => 'Administrative Division']);
        $wanted = Section::factory()->create(['division_id' => $division->id, 'name' => 'HRD Section']);
        Section::factory()->create(['division_id' => $division->id, 'name' => 'Budget Section']);
        Division::factory()->create(['name' => 'Medical Services Division']);

        $listed = $this->listed(['section' => $wanted->id]);

        $this->assertCount(1, $listed);
        $this->assertSame('Administrative Division', $listed->first()->name);
        $this->assertSame(['HRD Section'], $listed->first()->sections->pluck('name')->all());
    }

    public function test_the_search_matches_a_division_by_name_or_code(): void
    {
        Division::factory()->create(['name' => 'Administrative Division', 'code' => 'ADM']);
        Division::factory()->create(['name' => 'Medical Services Division', 'code' => 'MED']);

        $listed = $this->listed(['search' => 'ADM']);

        $this->assertCount(1, $listed);
        $this->assertSame('Administrative Division', $listed->first()->name);
    }

    /** A search that matches a section brings its division with it. */
    public function test_the_search_matches_a_section_too(): void
    {
        $division = Division::factory()->create(['name' => 'Administrative Division']);
        Section::factory()->create(['division_id' => $division->id, 'name' => 'HRD Section']);
        Division::factory()->create(['name' => 'Medical Services Division']);

        $listed = $this->listed(['search' => 'HRD']);

        $this->assertCount(1, $listed);
        $this->assertSame('Administrative Division', $listed->first()->name);
    }

    public function test_the_filters_are_offered_on_the_page(): void
    {
        $division = Division::factory()->create(['name' => 'Administrative Division']);
        Section::factory()->create(['division_id' => $division->id, 'name' => 'HRD Section']);

        $this->actingAs($this->admin())
            ->get(route('admin.divisions.index'))
            ->assertOk()
            ->assertSee('name="division"', false)
            ->assertSee('name="section"', false);
    }

    // -----------------------------------------------------------------
    // What paging must not break
    // -----------------------------------------------------------------

    /**
     * The New Section form has to offer every division in the hospital.
     *
     * Feeding it the paged list would silently shrink it to whatever happened
     * to be on screen - so a division on page 2 could never be given a new
     * section from page 1.
     */
    public function test_the_new_section_form_still_offers_every_division(): void
    {
        Division::factory()->count(10)->create();
        $offPage = Division::factory()->create(['name' => 'Zulu Division']);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.divisions.index'))
            ->assertOk()
            ->getContent();

        preg_match('/<select name="division_id".*?<\/select>/s', $html, $matches);

        $this->assertNotEmpty($matches, 'The New Section form should be on the page.');
        $this->assertStringContainsString('value="' . $offPage->id . '"', $matches[0]);
    }

    /** The same list, and the same trap, when a filter is applied. */
    public function test_a_filter_does_not_shrink_the_new_section_form(): void
    {
        $shown = Division::factory()->create(['name' => 'Administrative Division']);
        $hidden = Division::factory()->create(['name' => 'Medical Services Division']);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.divisions.index', ['division' => $shown->id]))
            ->assertOk()
            ->getContent();

        preg_match('/<select name="division_id".*?<\/select>/s', $html, $matches);

        $this->assertStringContainsString('value="' . $hidden->id . '"', $matches[0]);
    }
}
