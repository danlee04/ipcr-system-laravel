<?php

namespace Tests\Feature\Admin;

use App\Enums\FunctionCategory;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\JobFunction;
use App\Models\Position;
use App\Models\User;
use App\Services\FunctionCatalogService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Two questions, not one.
 *
 *   Category   - what kind of function this is: strategic, core or support.
 *   Applies to - who it reaches: everyone, whoever holds a position, or
 *                whoever holds a designation.
 *
 * They used to be tangled. Choosing "strategic" forced a designation and
 * choosing "core" forced a position, as if the category decided the audience.
 * It does not: a Section Head's strategic commitments belong to their post,
 * and an OIC's core duties belong to their designation.
 */
class FunctionReachTest extends TestCase
{
    use RefreshDatabase;

    private FunctionCatalogService $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->catalog = app(FunctionCatalogService::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category'          => FunctionCategory::Strategic->value,
            'applies_to'        => 'position',
            'title'             => 'Leads the quality improvement programme',
            'success_indicator' => 'Two reviews completed each quarter',
        ], $overrides);
    }

    /** All three of them - there is no fourth. */
    public static function ratedCategories(): array
    {
        return [
            'strategic' => ['strategic'],
            'core'      => ['core'],
            'support'   => ['support'],
        ];
    }

    // -----------------------------------------------------------------
    // Saving either link, whatever the category
    // -----------------------------------------------------------------

    #[DataProvider('ratedCategories')]
    public function test_any_rated_category_can_be_attached_to_a_position(string $category): void
    {
        $position = Position::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload([
                'category'    => $category,
                'position_id' => $position->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('job_functions', [
            'category' => $category, 'position_id' => $position->id, 'designation_id' => null,
        ]);
    }

    #[DataProvider('ratedCategories')]
    public function test_any_rated_category_can_be_attached_to_a_designation(string $category): void
    {
        $designation = Designation::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload([
                'category'       => $category,
                'applies_to'     => 'designation',
                'designation_id' => $designation->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('job_functions', [
            'category' => $category, 'designation_id' => $designation->id, 'position_id' => null,
        ]);
    }

    /** Whatever the category, the chosen route still needs its link. */
    #[DataProvider('ratedCategories')]
    public function test_a_chosen_route_needs_its_link(string $category): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload(['category' => $category]))
            ->assertSessionHasErrors('position_id');

        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload([
                'category'   => $category,
                'applies_to' => 'designation',
            ]))
            ->assertSessionHasErrors('designation_id');

        $this->assertDatabaseCount('job_functions', 0);
    }

    // -----------------------------------------------------------------
    // Reaching the employee
    // -----------------------------------------------------------------

    #[DataProvider('ratedCategories')]
    public function test_a_function_on_a_position_reaches_whoever_holds_it(string $category): void
    {
        $position = Position::factory()->create();
        $employee = Employee::factory()->create(['position_id' => $position->id]);

        $function = JobFunction::create([
            'category' => $category, 'position_id' => $position->id,
            'title' => 'From the position', 'is_active' => true,
        ]);

        $this->assertTrue(
            $this->catalog->availableFor($employee)->{$category}->contains($function),
            "A {$category} function on a position should reach whoever holds that position."
        );
    }

    #[DataProvider('ratedCategories')]
    public function test_a_function_on_a_designation_reaches_whoever_holds_it(string $category): void
    {
        $designation = Designation::factory()->create();
        $employee = Employee::factory()->create();
        $employee->designations()->attach($designation->id, ['is_active' => true]);

        $function = JobFunction::create([
            'category' => $category, 'designation_id' => $designation->id,
            'title' => 'From the designation', 'is_active' => true,
        ]);

        $this->assertTrue(
            $this->catalog->availableFor($employee->fresh())->{$category}->contains($function),
            "A {$category} function on a designation should reach whoever holds that designation."
        );
    }

    #[DataProvider('ratedCategories')]
    public function test_both_sources_arrive_together(string $category): void
    {
        $position = Position::factory()->create();
        $designation = Designation::factory()->create();

        $employee = Employee::factory()->create(['position_id' => $position->id]);
        $employee->designations()->attach($designation->id, ['is_active' => true]);

        JobFunction::create([
            'category' => $category, 'position_id' => $position->id,
            'title' => 'From the position', 'is_active' => true,
        ]);
        JobFunction::create([
            'category' => $category, 'designation_id' => $designation->id,
            'title' => 'From the designation', 'is_active' => true,
        ]);

        $this->assertCount(2, $this->catalog->availableFor($employee->fresh())->{$category});
    }

    #[DataProvider('ratedCategories')]
    public function test_a_designation_they_no_longer_hold_reaches_them_with_nothing(string $category): void
    {
        $designation = Designation::factory()->create();
        $employee = Employee::factory()->create();
        $employee->designations()->attach($designation->id, ['is_active' => false]);

        JobFunction::create([
            'category' => $category, 'designation_id' => $designation->id,
            'title' => 'Former duty', 'is_active' => true,
        ]);

        $this->assertCount(0, $this->catalog->availableFor($employee->fresh())->{$category});
    }

    #[DataProvider('ratedCategories')]
    public function test_somebody_elses_position_does_not_leak_across(string $category): void
    {
        $employee = Employee::factory()->create(['position_id' => Position::factory()->create()->id]);

        JobFunction::create([
            'category' => $category, 'position_id' => Position::factory()->create()->id,
            'title' => 'Somebody elses work', 'is_active' => true,
        ]);

        $this->assertCount(0, $this->catalog->availableFor($employee)->{$category});
    }

    // -----------------------------------------------------------------
    // The form
    // -----------------------------------------------------------------

    public function test_the_form_asks_who_the_function_applies_to(): void
    {
        Position::factory()->create();
        Designation::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->assertSee('name="applies_to" value="position"', false)
            ->assertSee('name="applies_to" value="designation"', false);
    }

    /**
     * A position sits in exactly one section and one division, so naming it
     * says both. The two selects above it fill themselves in.
     */
    public function test_choosing_a_position_fills_in_its_division_and_section(): void
    {
        $division = \App\Models\Division::factory()->create();
        $section = \App\Models\Section::factory()->create(['division_id' => $division->id]);
        $position = Position::factory()->create(['section_id' => $section->id]);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->getContent();

        // The option carries where it sits, and the select copies it up.
        $this->assertStringContainsString('data-section="' . $section->id . '"', $html);
        $this->assertStringContainsString('data-division="' . $division->id . '"', $html);
        $this->assertStringContainsString('division = chosen?.dataset.division', $html);
        $this->assertStringContainsString('section = chosen?.dataset.section', $html);

        $this->assertGreaterThan(0, $position->id);
    }

    /**
     * And narrowing from above clears what sat below it.
     *
     * An option hidden by the filter is still a selected option, and would
     * still be submitted - the same trap as every other hidden field.
     */
    public function test_narrowing_the_division_clears_the_position(): void
    {
        Position::factory()->create();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('x-on:change="section = \'\'; position = \'\'"', $html);
        $this->assertStringContainsString('x-on:change="position = \'\'"', $html);
    }

    /**
     * The choice belongs to every category, not to one of them. It used to be
     * shown only under Core, which is what tied the audience to the kind of
     * work - and the whole question now stands outside the category entirely.
     */
    public function test_the_choice_is_not_hidden_behind_any_category(): void
    {
        Position::factory()->create();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString("category === 'core'", $html);
        $this->assertStringNotContainsString("category === 'common'", $html);
        $this->assertStringNotContainsString("category !== 'common'", $html);
    }
}
