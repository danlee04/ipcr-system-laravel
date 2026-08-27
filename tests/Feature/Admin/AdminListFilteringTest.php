<?php

namespace Tests\Feature\Admin;

use App\Enums\FunctionCategory;
use App\Models\Designation;
use App\Models\Division;
use App\Models\Employee;
use App\Models\JobFunction;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Search, filtering and pagination on the admin lists that grow.
 *
 * Employees, job functions and job titles all run to hundreds of rows in a
 * real hospital. Divisions, sections and rating periods stay small and are
 * deliberately left whole.
 */
class AdminListFilteringTest extends TestCase
{
    use RefreshDatabase;

    private const PER_PAGE = 20;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    // -----------------------------------------------------------------
    // Employees
    // -----------------------------------------------------------------

    public function test_the_employee_list_is_paginated(): void
    {
        Employee::factory()->count(self::PER_PAGE + 5)->create();

        $response = $this->actingAs($this->admin())->get(route('admin.employees.index'))->assertOk();

        $this->assertCount(self::PER_PAGE, $response->viewData('employees'));
        $this->assertSame(self::PER_PAGE + 5, $response->viewData('employees')->total());
    }

    /** The paginator view is overridden for Tailwind v4; make sure it renders. */
    public function test_the_paginator_is_rendered_with_a_range_and_a_next_link(): void
    {
        Employee::factory()->count(self::PER_PAGE + 5)->create();

        $this->actingAs($this->admin())
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee('Showing')
            ->assertSee('Next')
            ->assertSee(route('admin.employees.index', ['page' => 2]), false);
    }

    public function test_no_paginator_is_shown_when_everything_fits_on_one_page(): void
    {
        Employee::factory()->count(3)->create();

        $this->actingAs($this->admin())
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertDontSee('Pagination Navigation');
    }

    public function test_the_second_page_holds_the_rest(): void
    {
        Employee::factory()->count(self::PER_PAGE + 5)->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.employees.index', ['page' => 2]))
            ->assertOk();

        $this->assertCount(5, $response->viewData('employees'));
    }

    public function test_employees_can_be_searched_by_name(): void
    {
        Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        Employee::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.employees.index', ['search' => 'santos']))
            ->assertOk();

        $names = $response->viewData('employees')->pluck('last_name');
        $this->assertContains('Santos', $names);
        $this->assertNotContains('Dela Cruz', $names);
    }

    public function test_employees_can_be_searched_by_employee_number(): void
    {
        Employee::factory()->create(['employee_number' => 'DTRC-9001']);
        Employee::factory()->create(['employee_number' => 'DTRC-9002']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.employees.index', ['search' => '9001']))
            ->assertOk();

        $this->assertSame(['DTRC-9001'], $response->viewData('employees')->pluck('employee_number')->all());
    }

    public function test_employees_can_be_searched_by_the_email_on_their_account(): void
    {
        $user = User::factory()->create(['email' => 'maria@dtrc.test']);
        Employee::factory()->create(['user_id' => $user->id, 'last_name' => 'Santos']);
        Employee::factory()->create(['last_name' => 'Reyes']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.employees.index', ['search' => 'maria@dtrc']))
            ->assertOk();

        $this->assertSame(['Santos'], $response->viewData('employees')->pluck('last_name')->all());
    }

    public function test_employees_can_be_filtered_by_division(): void
    {
        $medical = Division::factory()->create();
        Employee::factory()->create(['division_id' => $medical->id, 'last_name' => 'Inside']);
        Employee::factory()->create(['last_name' => 'Outside']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.employees.index', ['division' => $medical->id]))
            ->assertOk();

        $this->assertSame(['Inside'], $response->viewData('employees')->pluck('last_name')->all());
    }

    public function test_employees_can_be_filtered_by_section(): void
    {
        $nursing = Section::factory()->create();
        Employee::factory()->create(['section_id' => $nursing->id, 'last_name' => 'Inside']);
        Employee::factory()->create(['last_name' => 'Outside']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.employees.index', ['section' => $nursing->id]))
            ->assertOk();

        $this->assertSame(['Inside'], $response->viewData('employees')->pluck('last_name')->all());
    }

    public function test_employees_can_be_filtered_by_status(): void
    {
        Employee::factory()->create(['is_active' => true, 'last_name' => 'Working']);
        Employee::factory()->create(['is_active' => false, 'last_name' => 'Retired']);

        $active = $this->actingAs($this->admin())
            ->get(route('admin.employees.index', ['status' => 'active']))->assertOk();
        $this->assertSame(['Working'], $active->viewData('employees')->pluck('last_name')->all());

        $inactive = $this->actingAs($this->admin())
            ->get(route('admin.employees.index', ['status' => 'inactive']))->assertOk();
        $this->assertSame(['Retired'], $inactive->viewData('employees')->pluck('last_name')->all());
    }

    /** Losing the filters on page 2 makes a filtered list useless. */
    public function test_filters_survive_paging(): void
    {
        $division = Division::factory()->create();
        Employee::factory()->count(self::PER_PAGE + 3)->create(['division_id' => $division->id]);
        Employee::factory()->count(5)->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.employees.index', ['division' => $division->id, 'page' => 2]))
            ->assertOk();

        $this->assertCount(3, $response->viewData('employees'));
        $this->assertStringContainsString('division=' . $division->id, (string) $response->viewData('employees')->url(1));
    }

    // -----------------------------------------------------------------
    // Job functions
    // -----------------------------------------------------------------

    public function test_the_job_function_list_is_paginated(): void
    {
        $position = Position::factory()->create();

        for ($i = 0; $i < self::PER_PAGE + 4; $i++) {
            JobFunction::create([
                'category' => FunctionCategory::Core, 'title' => "Function {$i}",
                'position_id' => $position->id, 'is_active' => true,
            ]);
        }

        $response = $this->actingAs($this->admin())->get(route('admin.functions.index'))->assertOk();

        $this->assertCount(self::PER_PAGE, $response->viewData('functions'));
    }

    public function test_job_functions_can_be_searched_by_title(): void
    {
        JobFunction::create(['category' => FunctionCategory::Support, 'title' => 'Attends agency meetings', 'is_active' => true]);
        JobFunction::create(['category' => FunctionCategory::Support, 'title' => 'Observes working hours', 'is_active' => true]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.functions.index', ['search' => 'meetings']))
            ->assertOk();

        $this->assertSame(['Attends agency meetings'], $response->viewData('functions')->pluck('title')->all());
    }

    public function test_job_functions_can_be_filtered_by_category(): void
    {
        $position = Position::factory()->create();
        JobFunction::create(['category' => FunctionCategory::Core, 'title' => 'A core one', 'position_id' => $position->id, 'is_active' => true]);
        JobFunction::create(['category' => FunctionCategory::Support, 'title' => 'A common one', 'is_active' => true]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.functions.index', ['category' => 'support']))
            ->assertOk();

        $this->assertSame(['A common one'], $response->viewData('functions')->pluck('title')->all());
    }

    // -----------------------------------------------------------------
    // Job titles
    // -----------------------------------------------------------------

    public function test_positions_are_paginated(): void
    {
        Position::factory()->count(self::PER_PAGE + 6)->create();

        $response = $this->actingAs($this->admin())->get(route('admin.positions.index'))->assertOk();

        $this->assertCount(self::PER_PAGE, $response->viewData('positions'));
    }

    public function test_positions_can_be_searched(): void
    {
        Position::factory()->create(['title' => 'Nurse II']);
        Position::factory()->create(['title' => 'Medical Officer IV']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.positions.index', ['search' => 'nurse']))
            ->assertOk();

        $this->assertSame(['Nurse II'], $response->viewData('positions')->pluck('title')->all());
    }

    public function test_designations_are_searched_on_their_own_tab(): void
    {
        Designation::factory()->create(['title' => 'OIC - Budget Officer']);
        Designation::factory()->create(['title' => 'OIC - HRMO']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.positions.index', ['tab' => 'designations', 'search' => 'budget']))
            ->assertOk();

        $this->assertSame(['OIC - Budget Officer'], $response->viewData('designations')->pluck('title')->all());
    }

    /** The tab counts describe the whole set, not the filtered page. */
    public function test_the_tab_counts_are_not_narrowed_by_the_search(): void
    {
        Position::factory()->count(3)->create(['title' => 'Nurse II']);
        Designation::factory()->count(2)->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.positions.index', ['search' => 'nothing matches this']))
            ->assertOk();

        $this->assertSame(3, $response->viewData('positionCount'));
        $this->assertSame(2, $response->viewData('designationCount'));
    }
}
