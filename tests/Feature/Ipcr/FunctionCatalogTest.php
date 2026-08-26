<?php

namespace Tests\Feature\Ipcr;

use App\Enums\FunctionCategory;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\JobFunction;
use App\Models\Position;
use App\Services\FunctionCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Which suggestions an employee sees when building an IPCR.
 *
 * Nothing here is added automatically. The point of the catalog is reach: a
 * function that reaches nobody is in the database and nowhere else.
 *
 *   core      -> their plantilla position AND their active designations
 *   strategic -> their active designations
 *   support   -> their active designations
 *   common    -> everyone
 *
 * Core reaching through a designation is the part that had been missing. A
 * designation carries core duties of its own, and filing those as "support"
 * put them in a category they do not belong to and weighted them at 20%
 * instead of 80%.
 */
class FunctionCatalogTest extends TestCase
{
    use RefreshDatabase;

    private FunctionCatalogService $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = app(FunctionCatalogService::class);
    }

    private function employeeWithDesignation(Designation $designation, bool $active = true): Employee
    {
        $employee = Employee::factory()->create(['position_id' => Position::factory()->create()->id]);

        $employee->designations()->attach($designation->id, ['is_active' => $active]);

        return $employee->fresh();
    }

    public function test_core_functions_reach_an_employee_through_their_position(): void
    {
        $position = Position::factory()->create();
        $employee = Employee::factory()->create(['position_id' => $position->id]);

        $function = JobFunction::create([
            'category'    => FunctionCategory::Core,
            'position_id' => $position->id,
            'title'       => 'Runs the ward',
            'is_active'   => true,
        ]);

        $this->assertTrue($this->catalog->availableFor($employee)->core->contains($function));
    }

    public function test_core_functions_also_reach_them_through_an_active_designation(): void
    {
        $designation = Designation::factory()->create();
        $employee = $this->employeeWithDesignation($designation);

        $function = JobFunction::create([
            'category'       => FunctionCategory::Core,
            'designation_id' => $designation->id,
            'title'          => 'Leads infection control rounds',
            'is_active'      => true,
        ]);

        $this->assertTrue($this->catalog->availableFor($employee)->core->contains($function));
    }

    /** Both sources at once, with no duplication between them. */
    public function test_both_sources_are_offered_together(): void
    {
        $designation = Designation::factory()->create();
        $employee = $this->employeeWithDesignation($designation);

        JobFunction::create([
            'category' => FunctionCategory::Core, 'position_id' => $employee->position_id,
            'title' => 'From the position', 'is_active' => true,
        ]);
        JobFunction::create([
            'category' => FunctionCategory::Core, 'designation_id' => $designation->id,
            'title' => 'From the designation', 'is_active' => true,
        ]);

        $core = $this->catalog->availableFor($employee)->core;

        $this->assertCount(2, $core);
        $this->assertSame(['From the designation', 'From the position'], $core->pluck('title')->all());
    }

    public function test_a_designation_the_employee_no_longer_holds_reaches_them_with_nothing(): void
    {
        $designation = Designation::factory()->create();
        $employee = $this->employeeWithDesignation($designation, active: false);

        JobFunction::create([
            'category' => FunctionCategory::Core, 'designation_id' => $designation->id,
            'title' => 'Former duty', 'is_active' => true,
        ]);

        $this->assertCount(0, $this->catalog->availableFor($employee)->core);
    }

    public function test_another_designations_core_function_does_not_leak_across(): void
    {
        $employee = $this->employeeWithDesignation(Designation::factory()->create());

        JobFunction::create([
            'category' => FunctionCategory::Core, 'designation_id' => Designation::factory()->create()->id,
            'title' => 'Somebody elses duty', 'is_active' => true,
        ]);

        $this->assertCount(0, $this->catalog->availableFor($employee)->core);
    }

    public function test_a_deactivated_core_function_is_not_offered(): void
    {
        $designation = Designation::factory()->create();
        $employee = $this->employeeWithDesignation($designation);

        JobFunction::create([
            'category' => FunctionCategory::Core, 'designation_id' => $designation->id,
            'title' => 'Retired duty', 'is_active' => false,
        ]);

        $this->assertCount(0, $this->catalog->availableFor($employee)->core);
    }

    /** An employee with no position still gets their designation's core work. */
    public function test_core_reaches_an_employee_who_holds_no_position(): void
    {
        $designation = Designation::factory()->create();
        $employee = Employee::factory()->create(['position_id' => null]);
        $employee->designations()->attach($designation->id, ['is_active' => true]);

        JobFunction::create([
            'category' => FunctionCategory::Core, 'designation_id' => $designation->id,
            'title' => 'Designated duty', 'is_active' => true,
        ]);

        $this->assertCount(1, $this->catalog->availableFor($employee->fresh())->core);
    }
}
