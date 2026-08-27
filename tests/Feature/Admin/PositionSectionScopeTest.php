<?php

namespace Tests\Feature\Admin;

use App\Enums\FunctionCategory;
use App\Models\Division;
use App\Models\Employee;
use App\Models\JobFunction;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use App\Services\OrgDeletionGuard;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A position belongs to a section, and a section to a division.
 *
 * That chain is what lets the Functions screen narrow Division -> Section ->
 * Position: the two upper dropdowns exist to find the right position, not to
 * scope the function directly.
 */
class PositionSectionScopeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function sectionIn(Division $division, string $name = 'Nursing Section'): Section
    {
        return Section::factory()->create(['division_id' => $division->id, 'name' => $name]);
    }

    // -----------------------------------------------------------------
    // The position carries the section
    // -----------------------------------------------------------------

    public function test_a_position_can_be_created_inside_a_section(): void
    {
        $section = $this->sectionIn(Division::factory()->create());

        $this->actingAs($this->admin())
            ->post(route('admin.positions.store'), [
                'title' => 'Nurse II', 'section_id' => $section->id,
            ])
            ->assertRedirect(route('admin.positions.index'));

        $this->assertSame($section->id, Position::where('title', 'Nurse II')->first()->section_id);
    }

    /** Not every post sits in a section - some are office-wide. */
    public function test_the_section_is_optional(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.positions.store'), ['title' => 'Chief of Hospital'])
            ->assertSessionHasNoErrors();

        $this->assertNull(Position::where('title', 'Chief of Hospital')->first()->section_id);
    }

    public function test_an_unknown_section_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.positions.store'), ['title' => 'Nurse II', 'section_id' => 9999])
            ->assertSessionHasErrors('section_id');
    }

    public function test_the_division_comes_from_the_section(): void
    {
        $division = Division::factory()->create(['name' => 'Medical Services']);
        $position = Position::factory()->create(['section_id' => $this->sectionIn($division)->id]);

        $this->assertSame($division->id, $position->fresh()->division?->id);
    }

    public function test_a_position_with_no_section_has_no_division(): void
    {
        $this->assertNull(Position::factory()->create(['section_id' => null])->division);
    }

    public function test_an_admin_can_move_a_position_to_another_section(): void
    {
        $position = Position::factory()->create();
        $target = $this->sectionIn(Division::factory()->create(), 'Pharmacy Section');

        $this->actingAs($this->admin())->put(route('admin.positions.update', $position), [
            'title' => $position->title, 'section_id' => $target->id,
        ]);

        $this->assertSame($target->id, $position->fresh()->section_id);
    }

    // -----------------------------------------------------------------
    // A section holding positions cannot be deleted
    // -----------------------------------------------------------------

    public function test_a_section_with_positions_is_not_deletable(): void
    {
        $section = $this->sectionIn(Division::factory()->create());
        Position::factory()->create(['section_id' => $section->id]);

        $report = app(OrgDeletionGuard::class)->for($section->fresh());

        $this->assertFalse($report->deletable);
        $this->assertStringContainsString('position', $report->message());
    }

    public function test_a_section_with_nothing_in_it_is_still_deletable(): void
    {
        $section = $this->sectionIn(Division::factory()->create());

        $this->assertTrue(app(OrgDeletionGuard::class)->for($section)->deletable);
    }

    // -----------------------------------------------------------------
    // Narrowing the Positions screen
    // -----------------------------------------------------------------

    public function test_positions_can_be_filtered_by_division(): void
    {
        $wanted = Division::factory()->create();
        Position::factory()->create(['title' => 'Inside', 'section_id' => $this->sectionIn($wanted)->id]);
        Position::factory()->create(['title' => 'Outside', 'section_id' => $this->sectionIn(Division::factory()->create())->id]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.positions.index', ['division' => $wanted->id]))
            ->assertOk();

        $this->assertSame(['Inside'], $response->viewData('positions')->pluck('title')->all());
    }

    public function test_positions_can_be_filtered_by_section(): void
    {
        $section = $this->sectionIn(Division::factory()->create());
        Position::factory()->create(['title' => 'Inside', 'section_id' => $section->id]);
        Position::factory()->create(['title' => 'Outside']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.positions.index', ['section' => $section->id]))
            ->assertOk();

        $this->assertSame(['Inside'], $response->viewData('positions')->pluck('title')->all());
    }

    // -----------------------------------------------------------------
    // Narrowing the Functions screen through the position
    // -----------------------------------------------------------------

    public function test_functions_can_be_filtered_by_division_through_their_position(): void
    {
        $division = Division::factory()->create();
        $inside = Position::factory()->create(['section_id' => $this->sectionIn($division)->id]);
        $outside = Position::factory()->create();

        JobFunction::create(['category' => FunctionCategory::Core, 'title' => 'Inside one', 'position_id' => $inside->id, 'is_active' => true]);
        JobFunction::create(['category' => FunctionCategory::Core, 'title' => 'Outside one', 'position_id' => $outside->id, 'is_active' => true]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.functions.index', ['division' => $division->id]))
            ->assertOk();

        $this->assertSame(['Inside one'], $response->viewData('functions')->pluck('title')->all());
    }

    public function test_functions_can_be_filtered_by_section(): void
    {
        $section = $this->sectionIn(Division::factory()->create());
        $inside = Position::factory()->create(['section_id' => $section->id]);

        JobFunction::create(['category' => FunctionCategory::Core, 'title' => 'Inside one', 'position_id' => $inside->id, 'is_active' => true]);
        JobFunction::create(['category' => FunctionCategory::Core, 'title' => 'Outside one', 'position_id' => Position::factory()->create()->id, 'is_active' => true]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.functions.index', ['section' => $section->id]))
            ->assertOk();

        $this->assertSame(['Inside one'], $response->viewData('functions')->pluck('title')->all());
    }

    public function test_functions_can_be_filtered_by_position(): void
    {
        $position = Position::factory()->create();
        JobFunction::create(['category' => FunctionCategory::Core, 'title' => 'Wanted', 'position_id' => $position->id, 'is_active' => true]);
        JobFunction::create(['category' => FunctionCategory::Core, 'title' => 'Other', 'position_id' => Position::factory()->create()->id, 'is_active' => true]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.functions.index', ['position' => $position->id]))
            ->assertOk();

        $this->assertSame(['Wanted'], $response->viewData('functions')->pluck('title')->all());
    }

    /**
     * Common functions belong to everyone, so a division or section filter
     * must not hide them - the old screen said exactly this.
     */
    public function test_the_filters_do_not_hide_common_functions(): void
    {
        $division = Division::factory()->create();
        $position = Position::factory()->create(['section_id' => $this->sectionIn($division)->id]);

        JobFunction::create(['category' => FunctionCategory::Core, 'title' => 'A core one', 'position_id' => $position->id, 'is_active' => true]);
        JobFunction::create(['category' => FunctionCategory::Support, 'title' => 'A common one', 'is_active' => true]);

        $titles = $this->actingAs($this->admin())
            ->get(route('admin.functions.index', ['division' => $division->id]))
            ->assertOk()
            ->viewData('functions')->pluck('title')->all();

        $this->assertContains('A common one', $titles);
        $this->assertContains('A core one', $titles);
    }

    // -----------------------------------------------------------------
    // What the screens offer
    // -----------------------------------------------------------------

    public function test_the_position_form_offers_a_section_select(): void
    {
        $this->sectionIn(Division::factory()->create(), 'Nursing Section');

        $this->actingAs($this->admin())
            ->get(route('admin.positions.index'))
            ->assertOk()
            ->assertSee('name="section_id"', false)
            ->assertSee('Nursing Section');
    }

    /** The section options carry their division so the cascade can filter them. */
    public function test_the_section_options_declare_their_division(): void
    {
        $division = Division::factory()->create();
        $section = $this->sectionIn($division);

        $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->assertSee('data-division="' . $division->id . '"', false);
    }

    public function test_the_position_options_declare_their_section(): void
    {
        $section = $this->sectionIn(Division::factory()->create());
        Position::factory()->create(['section_id' => $section->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->assertSee('data-section="' . $section->id . '"', false);
    }

    public function test_the_positions_list_shows_which_section_each_belongs_to(): void
    {
        $division = Division::factory()->create(['name' => 'Medical Services']);
        Position::factory()->create(['title' => 'Nurse II', 'section_id' => $this->sectionIn($division)->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.positions.index'))
            ->assertOk()
            ->assertSee('Nursing Section')
            ->assertSee('Medical Services');
    }

    public function test_an_employee_still_reaches_the_core_functions_of_their_position(): void
    {
        $section = $this->sectionIn(Division::factory()->create());
        $position = Position::factory()->create(['section_id' => $section->id]);

        JobFunction::create([
            'category' => FunctionCategory::Core, 'title' => 'Provides patient care',
            'position_id' => $position->id, 'is_active' => true,
        ]);

        $employee = Employee::factory()->create(['position_id' => $position->id, 'section_id' => $section->id]);

        $catalog = app(\App\Services\FunctionCatalogService::class)->availableFor($employee);

        $this->assertSame(['Provides patient care'], $catalog->core->pluck('title')->all());
    }
}
