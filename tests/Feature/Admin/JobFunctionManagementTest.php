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

class JobFunctionManagementTest extends TestCase
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
            'default_weight'    => 30,
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // Creating
    // -----------------------------------------------------------------

    public function test_an_admin_can_add_a_core_function_to_a_position(): void
    {
        $position = Position::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.job-functions.store'), $this->payload(['position_id' => $position->id]))
            ->assertRedirect(route('admin.job-functions.index'));

        $this->assertDatabaseHas('job_functions', [
            'title' => 'Provides direct patient care', 'position_id' => $position->id, 'is_active' => true,
        ]);
    }

    public function test_an_admin_can_add_a_strategic_function_to_a_designation(): void
    {
        $designation = Designation::factory()->create();

        $this->actingAs($this->admin())->post(route('admin.job-functions.store'), $this->payload([
            'category' => FunctionCategory::Strategic->value,
            'title' => 'Prepares the annual budget proposal',
            'designation_id' => $designation->id,
        ]));

        $this->assertDatabaseHas('job_functions', [
            'title' => 'Prepares the annual budget proposal', 'designation_id' => $designation->id,
        ]);
    }

    public function test_a_function_needs_a_title(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.job-functions.store'), $this->payload(['title' => '']))
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
            ->post(route('admin.job-functions.store'), $this->payload(['position_id' => null]))
            ->assertSessionHasErrors('position_id');
    }

    public function test_a_strategic_function_must_name_a_designation(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.job-functions.store'), $this->payload([
                'category' => FunctionCategory::Strategic->value, 'designation_id' => null,
            ]))
            ->assertSessionHasErrors('designation_id');
    }

    public function test_a_common_function_needs_neither_and_takes_a_rating_category(): void
    {
        $this->actingAs($this->admin())->post(route('admin.job-functions.store'), $this->payload([
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
            ->post(route('admin.job-functions.store'), $this->payload([
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

        $this->actingAs($this->admin())->put(route('admin.job-functions.update', $function), $this->payload([
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

        $this->actingAs($this->admin())->put(route('admin.job-functions.update', $function), $this->payload([
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

        $this->actingAs($this->admin())->patch(route('admin.job-functions.active', $function), ['active' => false]);
        $this->assertFalse($function->fresh()->is_active);

        $this->actingAs($this->admin())->patch(route('admin.job-functions.active', $function), ['active' => true]);
        $this->assertTrue($function->fresh()->is_active);
    }

    public function test_an_admin_can_delete_a_function(): void
    {
        $function = JobFunction::create([
            'category' => FunctionCategory::Common, 'title' => 'Disposable', 'is_active' => true,
        ]);

        $this->actingAs($this->admin())->delete(route('admin.job-functions.destroy', $function));

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
            ->get(route('admin.job-functions.index'))
            ->assertOk()
            ->assertSee('Provides direct patient care');
    }

    public function test_the_page_warns_about_common_functions_with_no_rating_category(): void
    {
        JobFunction::create([
            'category' => FunctionCategory::Common, 'title' => 'Attends meetings', 'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.job-functions.index'))
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
            ->get(route('admin.job-functions.index'))
            ->assertOk()
            ->assertDontSee('cannot be added to an IPCR');
    }

    // -----------------------------------------------------------------
    // Access
    // -----------------------------------------------------------------

    public function test_an_hr_user_can_manage_job_functions(): void
    {
        $this->actingAs($this->userWithRole('hr'))
            ->get(route('admin.job-functions.index'))
            ->assertOk();
    }

    public function test_a_plain_user_cannot_manage_job_functions(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.job-functions.store'), $this->payload())
            ->assertForbidden();
    }
}
