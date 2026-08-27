<?php

namespace Tests\Feature\Admin;

use App\Enums\FunctionCategory;
use App\Models\Designation;
use App\Models\JobFunction;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunctionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function admin(): User
    {
        return $this->userWithRole('admin');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category'          => FunctionCategory::Core->value,
            'applies_to'        => 'position',
            'title'             => 'Provides direct patient care',
            'success_indicator' => 'Patients seen within 30 minutes',

        ], $overrides);
    }

    // -----------------------------------------------------------------
    // Creating
    // -----------------------------------------------------------------

    public function test_an_admin_can_add_a_core_function_to_a_position(): void
    {
        $position = Position::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload(['position_id' => $position->id]))
            ->assertRedirect(route('admin.functions.index'));

        $this->assertDatabaseHas('job_functions', [
            'title' => 'Provides direct patient care', 'position_id' => $position->id, 'is_active' => true,
        ]);
    }

    public function test_an_admin_can_add_a_strategic_function_to_a_designation(): void
    {
        $designation = Designation::factory()->create();

        $this->actingAs($this->admin())->post(route('admin.functions.store'), $this->payload([
            'category' => FunctionCategory::Strategic->value,
            'title' => 'Prepares the annual budget proposal',
            'applies_to' => 'designation',
            'designation_id' => $designation->id,
        ]));

        $this->assertDatabaseHas('job_functions', [
            'title' => 'Prepares the annual budget proposal', 'designation_id' => $designation->id,
        ]);
    }

    /**
     * A designation is not a category of work.
     *
     * It carries core functions of its own - an Infection Control Officer has
     * core duties as one - so tying core to a plantilla position alone left no
     * way to record them, and forced them in as "support", which they are not.
     */
    public function test_an_admin_can_add_a_core_function_to_a_designation(): void
    {
        $designation = Designation::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload([
                'applies_to' => 'designation', 'designation_id' => $designation->id,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.functions.index'));

        $this->assertDatabaseHas('job_functions', [
            'title'          => 'Provides direct patient care',
            'category'       => FunctionCategory::Core->value,
            'designation_id' => $designation->id,
            'position_id'    => null,
        ]);
    }

    /**
     * The chosen branch decides which link is kept.
     *
     * Both used to be refused, because either could have been meant. Now the
     * form states which one it was on, so a leftover from a branch nobody is
     * looking at is discarded rather than treated as a second answer.
     */
    public function test_only_the_chosen_branch_keeps_its_link(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload([
                'applies_to'     => 'position',
                'position_id'    => Position::factory()->create()->id,
                'designation_id' => Designation::factory()->create()->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull(JobFunction::first()->designation_id, 'The branch not chosen is discarded.');
    }

    /** Nobody is offered a route without saying which one they took. */
    public function test_a_function_must_say_who_it_reaches(): void
    {
        $payload = $this->payload(['position_id' => Position::factory()->create()->id]);
        unset($payload['applies_to']);

        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $payload)
            ->assertSessionHasErrors('applies_to');
    }

    /**
     * The branches are shown and hidden by Alpine, and a hidden field is still
     * submitted. A leftover from a branch nobody is on has to be harmless.
     */
    public function test_a_leftover_from_another_branch_is_ignored(): void
    {
        $designation = Designation::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload([
                'category'       => FunctionCategory::Support->value,
                'applies_to'     => 'designation',
                'designation_id' => $designation->id,
                'position_id'    => Position::factory()->create()->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('job_functions', [
            'designation_id' => $designation->id,
            'position_id'    => null,
        ]);
    }

    public function test_the_form_offers_a_core_function_both_routes(): void
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
     * The form carries two selects called designation_id - one per route -
     * and a browser submits both. Every one of them must be disabled when its
     * branch is not the active one, or the wrong value wins silently.
     */
    public function test_every_branch_select_is_disabled_when_its_branch_is_inactive(): void
    {
        Position::factory()->create();
        Designation::factory()->create();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->getContent();

        preg_match_all('/<select name="(designation_id|position_id|rating_category)"[^>]*>/', $html, $matches);

        $this->assertNotEmpty($matches[0]);

        foreach ($matches[0] as $select) {
            $this->assertStringContainsString(':disabled=', $select, "Unguarded select: {$select}");
        }
    }

    /**
     * A designation belongs to no division, so a division filter cannot say
     * anything about a function attached to one. Hiding it would look like the
     * filter had found nothing, when it had only asked the wrong question.
     */
    public function test_a_division_filter_does_not_hide_a_core_function_on_a_designation(): void
    {
        $division = \App\Models\Division::factory()->create();
        $section = \App\Models\Section::factory()->create(['division_id' => $division->id]);
        $position = Position::factory()->create(['section_id' => $section->id]);

        JobFunction::create([
            'category' => FunctionCategory::Core, 'position_id' => $position->id,
            'title' => 'Tied to a position', 'is_active' => true,
        ]);
        JobFunction::create([
            'category' => FunctionCategory::Core, 'designation_id' => Designation::factory()->create()->id,
            'title' => 'Tied to a designation', 'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.functions.index', ['division' => $division->id]))
            ->assertOk()
            ->assertSee('Tied to a position')
            ->assertSee('Tied to a designation');
    }

    public function test_a_function_needs_a_title(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload(['title' => '']))
            ->assertSessionHasErrors('title');
    }

    /**
     * FunctionCatalogService finds core functions by position and strategic or
     * support by designation. Without the matching link the function is in the
     * catalog but reaches nobody.
     */
    public function test_a_core_function_must_name_a_position(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload(['position_id' => null]))
            ->assertSessionHasErrors('position_id');
    }

    /**
     * A strategic function needs one of the two links, not a designation in
     * particular - the category stopped deciding the audience. See
     * FunctionReachTest for the rule across every category.
     */
    public function test_a_strategic_function_must_name_somebody(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload([
                'category' => FunctionCategory::Strategic->value,
                'designation_id' => null, 'position_id' => null,
            ]))
            ->assertSessionHasErrors('position_id');

        $this->assertDatabaseCount('job_functions', 0);
    }

    // -----------------------------------------------------------------
    // Updating, deactivating, deleting
    // -----------------------------------------------------------------

    public function test_an_admin_can_edit_a_function(): void
    {
        $position = Position::factory()->create();
        $function = JobFunction::create([
            'category' => FunctionCategory::Core, 'title' => 'Old', 'position_id' => $position->id, 'is_active' => true,
        ]);

        $this->actingAs($this->admin())->put(route('admin.functions.update', $function), $this->payload([
            'title' => 'New', 'position_id' => $position->id,
        ]));

        $this->assertSame('New', $function->fresh()->title);
    }

    public function test_an_admin_can_deactivate_and_reactivate_a_function(): void
    {
        $function = JobFunction::create([
            'category' => FunctionCategory::Support, 'title' => 'A function', 'is_active' => true,
        ]);

        $this->actingAs($this->admin())->patch(route('admin.functions.active', $function), ['active' => false]);
        $this->assertFalse($function->fresh()->is_active);

        $this->actingAs($this->admin())->patch(route('admin.functions.active', $function), ['active' => true]);
        $this->assertTrue($function->fresh()->is_active);
    }

    public function test_an_admin_can_delete_a_function(): void
    {
        $function = JobFunction::create([
            'category' => FunctionCategory::Support, 'title' => 'Disposable', 'is_active' => true,
        ]);

        $this->actingAs($this->admin())->delete(route('admin.functions.destroy', $function));

        $this->assertDatabaseMissing('job_functions', ['id' => $function->id]);
    }

    // -----------------------------------------------------------------
    // The page
    // -----------------------------------------------------------------

    public function test_the_page_lists_functions(): void
    {
        $position = Position::factory()->create();
        JobFunction::create([
            'category' => FunctionCategory::Core, 'title' => 'Provides direct patient care',
            'position_id' => $position->id, 'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->assertSee('Provides direct patient care');
    }

    // -----------------------------------------------------------------
    // Access
    // -----------------------------------------------------------------

    public function test_an_hr_user_can_manage_job_functions(): void
    {
        $this->actingAs($this->userWithRole('hr'))
            ->get(route('admin.functions.index'))
            ->assertOk();
    }

    public function test_a_plain_user_cannot_manage_job_functions(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.functions.store'), $this->payload())
            ->assertForbidden();
    }
}
