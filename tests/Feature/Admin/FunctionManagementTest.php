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
            ->post(route('admin.functions.store'), $this->payload(['designation_id' => $designation->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.functions.index'));

        $this->assertDatabaseHas('job_functions', [
            'title'          => 'Provides direct patient care',
            'category'       => FunctionCategory::Core->value,
            'designation_id' => $designation->id,
            'position_id'    => null,
        ]);
    }

    public function test_a_core_function_names_a_position_or_a_designation_but_not_both(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload([
                'position_id'    => Position::factory()->create()->id,
                'designation_id' => Designation::factory()->create()->id,
            ]))
            ->assertSessionHasErrors('position_id');

        $this->assertDatabaseCount('job_functions', 0);
    }

    /**
     * The category selects are shown and hidden by Alpine, and a hidden field
     * is still submitted. Someone who looks at Common first and then switches
     * to Core sends a leftover rating category with the form.
     *
     * That used to be fatal - "the rating category field is prohibited",
     * pointing at a field they could no longer see. The controller already
     * ignores the value; the validator has no business refusing it.
     */
    public function test_a_rating_category_left_over_from_another_choice_is_ignored(): void
    {
        $position = Position::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload([
                'position_id'     => $position->id,
                'rating_category' => FunctionCategory::Support->value,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.functions.index'));

        $this->assertDatabaseHas('job_functions', [
            'position_id'     => $position->id,
            'category'        => FunctionCategory::Core->value,
            'rating_category' => null,
        ]);
    }

    /**
     * A stale position on a designation function used to be discarded, because
     * the category decided the audience and a support function could only ever
     * be a designation's.
     *
     * Both links are legitimate now, so two of them is not a leftover to clean
     * up - it is a question with two answers, and guessing would attach the
     * function to an audience nobody asked for. It is refused instead, and the
     * form's disabled branches are what stop it arising.
     */
    public function test_naming_both_a_position_and_a_designation_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload([
                'category'       => FunctionCategory::Support->value,
                'designation_id' => Designation::factory()->create()->id,
                'position_id'    => Position::factory()->create()->id,
            ]))
            ->assertSessionHasErrors('position_id');

        $this->assertDatabaseCount('job_functions', 0);
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

    public function test_a_common_function_needs_neither_and_takes_a_rating_category(): void
    {
        $this->actingAs($this->admin())->post(route('admin.functions.store'), $this->payload([
            'category'        => FunctionCategory::Common->value,
            'rating_category' => FunctionCategory::Support->value,
            'title'           => 'Observes official working hours',
        ]));

        $function = JobFunction::where('title', 'Observes official working hours')->first();
        $this->assertNotNull($function);
        $this->assertSame(FunctionCategory::Support, $function->ratingCategory());
    }

    public function test_a_common_functions_rating_category_cannot_itself_be_common(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload([
                'category'        => FunctionCategory::Common->value,
                'rating_category' => FunctionCategory::Common->value,
            ]))
            ->assertSessionHasErrors('rating_category');
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

    /** The screen exists largely to close this gap on the seeded data. */
    public function test_an_admin_can_file_an_orphaned_common_function_under_a_category(): void
    {
        $function = JobFunction::create([
            'category' => FunctionCategory::Common, 'title' => 'Attends meetings', 'is_active' => true,
        ]);

        $this->assertNull($function->ratingCategory());

        $this->actingAs($this->admin())->put(route('admin.functions.update', $function), $this->payload([
            'category' => FunctionCategory::Common->value,
            'title' => 'Attends meetings',
            'rating_category' => FunctionCategory::Core->value,
        ]));

        $this->assertSame(FunctionCategory::Core, $function->fresh()->ratingCategory());
    }

    public function test_an_admin_can_deactivate_and_reactivate_a_function(): void
    {
        $function = JobFunction::create([
            'category' => FunctionCategory::Common, 'title' => 'A function', 'is_active' => true,
        ]);

        $this->actingAs($this->admin())->patch(route('admin.functions.active', $function), ['active' => false]);
        $this->assertFalse($function->fresh()->is_active);

        $this->actingAs($this->admin())->patch(route('admin.functions.active', $function), ['active' => true]);
        $this->assertTrue($function->fresh()->is_active);
    }

    public function test_an_admin_can_delete_a_function(): void
    {
        $function = JobFunction::create([
            'category' => FunctionCategory::Common, 'title' => 'Disposable', 'is_active' => true,
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

    public function test_the_page_warns_about_common_functions_with_no_rating_category(): void
    {
        JobFunction::create([
            'category' => FunctionCategory::Common, 'title' => 'Attends meetings', 'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->assertSee('cannot be added to an IPCR');
    }

    public function test_no_warning_when_every_common_function_is_filed(): void
    {
        JobFunction::create([
            'category' => FunctionCategory::Common, 'rating_category' => FunctionCategory::Core,
            'title' => 'Attends meetings', 'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->assertDontSee('cannot be added to an IPCR');
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
