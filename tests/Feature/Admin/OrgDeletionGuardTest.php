<?php

namespace Tests\Feature\Admin;

use App\Models\Designation;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use App\Services\OrgDeletionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private OrgDeletionGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new OrgDeletionGuard();
    }

    public function test_an_unreferenced_division_is_deletable(): void
    {
        $report = $this->guard->for(Division::factory()->create());

        $this->assertTrue($report->deletable);
        $this->assertSame([], $report->blockers);
    }

    public function test_a_division_with_sections_is_blocked(): void
    {
        $division = Division::factory()->create();
        Section::factory()->count(3)->create(['division_id' => $division->id]);

        $report = $this->guard->for($division);

        $this->assertFalse($report->deletable);
        $this->assertSame(['sections' => 3], $report->blockers);
    }

    public function test_a_division_counts_sections_and_employees_separately(): void
    {
        $division = Division::factory()->create();
        Section::factory()->create(['division_id' => $division->id]);
        Employee::factory()->count(2)->create(['division_id' => $division->id]);

        $report = $this->guard->for($division);

        $this->assertSame(['sections' => 1, 'employees' => 2], $report->blockers);
    }

    public function test_a_section_is_blocked_by_employees(): void
    {
        $section = Section::factory()->create();
        Employee::factory()->create(['section_id' => $section->id]);

        $report = $this->guard->for($section);

        $this->assertFalse($report->deletable);
        $this->assertSame(['employees' => 1], $report->blockers);
    }

    public function test_a_position_is_blocked_by_employees(): void
    {
        $position = Position::factory()->create();
        Employee::factory()->create(['position_id' => $position->id]);

        $report = $this->guard->for($position);

        $this->assertSame(['employees' => 1], $report->blockers);
    }

    public function test_an_unreferenced_designation_is_deletable(): void
    {
        $this->assertTrue($this->guard->for(Designation::factory()->create())->deletable);
    }

    public function test_the_message_names_what_is_in_the_way(): void
    {
        $division = Division::factory()->create();
        Section::factory()->count(2)->create(['division_id' => $division->id]);

        $message = $this->guard->for($division)->message();

        $this->assertStringContainsString('2 sections', $message);
        $this->assertStringContainsString('Deactivate it instead', $message);
    }

    public function test_an_unsupported_model_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->guard->for(Employee::factory()->create());
    }
}
