<?php

namespace Tests\Feature\Ipcr;

use App\Models\Designation;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;
use App\Services\IpcrRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A designation moves where somebody works.
 *
 * The case this was built from: a records officer filed in HIM Section under
 * the RIT Division, designated OIC-HRMO under the Administrative Division. Her
 * IPCR fills with HRMO work - the functions come off the designation - and the
 * HIM section head has no sight of any of it.
 *
 * So the posting wins. Her sheet goes to the division actually running her,
 * and she appears on that division head's roster rather than her old one.
 *
 * A designation naming no office leaves everything alone. Plenty are a title
 * and nothing more.
 */
class DesignationPostingTest extends TestCase
{
    use RefreshDatabase;

    private Division $rit;

    private Section $him;

    private Division $administrative;

    private Employee $maryJane;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->rit = Division::factory()->create(['name' => 'RIT Division']);
        $this->him = Section::factory()->create(['name' => 'HIM Section', 'division_id' => $this->rit->id]);

        $this->administrative = Division::factory()->create(['name' => 'Administrative Division']);

        // Her plantilla position: HIM Section, RIT Division.
        $this->maryJane = Employee::factory()->create([
            'first_name'  => 'Mary Jane',
            'last_name'   => 'Guico',
            'section_id'  => $this->him->id,
            'division_id' => $this->rit->id,
        ]);

        // Heads for both, so a chain can be resolved either way.
        $this->him->update(['section_head_employee_id' => $this->headIn($this->him, 'Himhead')->id]);
        $this->rit->update(['division_head_employee_id' => $this->headIn($this->him, 'Rithead')->id]);
        $this->administrative->update([
            'division_head_employee_id' => $this->headIn(
                Section::factory()->create(['name' => 'Records', 'division_id' => $this->administrative->id]),
                'Adminhead',
            )->id,
        ]);

        Employee::factory()->create([
            'last_name' => 'Chief', 'is_chief_of_hospital' => true,
        ]);
    }

    private function headIn(Section $section, string $last): Employee
    {
        return Employee::factory()->create([
            'last_name'   => $last,
            'section_id'  => $section->id,
            'division_id' => $section->division_id,
        ]);
    }

    /** Designate her, to an office or to nothing at all. */
    private function designate(?Division $division = null, ?Section $section = null): Designation
    {
        $designation = Designation::factory()->create([
            'title'       => 'OIC - HRMO',
            'division_id' => $division?->id,
            'section_id'  => $section?->id,
        ]);

        $this->maryJane->designations()->attach($designation->id, ['is_active' => true]);

        return $designation;
    }

    private function chainFor(Employee $employee): array
    {
        $chain = app(IpcrRoutingService::class)->resolve($employee->fresh());

        return [$chain->assessor->last_name, $chain->finalApprover->last_name];
    }

    // -----------------------------------------------------------------
    // Where her sheet goes
    // -----------------------------------------------------------------

    /** Untouched while she holds nothing: HIM assesses, RIT gives the word. */
    public function test_without_a_designation_she_answers_to_her_own_section(): void
    {
        $this->assertSame(['Himhead', 'Rithead'], $this->chainFor($this->maryJane));
    }

    public function test_a_designation_naming_no_office_changes_nothing(): void
    {
        $this->designate();

        $this->assertSame(['Himhead', 'Rithead'], $this->chainFor($this->maryJane));
    }

    /**
     * Posted to a division and no section under it.
     *
     * An officer-in-charge runs a unit rather than sitting in one, so there is
     * no section head between them and the division - the same shape a section
     * head's own IPCR has.
     */
    public function test_posted_to_a_division_she_answers_to_its_head(): void
    {
        $this->designate(division: $this->administrative);

        $this->assertSame(['Adminhead', 'Chief'], $this->chainFor($this->maryJane));
    }

    /** Posted into a section, she answers to that section's head. */
    public function test_posted_to_a_section_she_answers_to_its_head(): void
    {
        $records = Section::factory()->create([
            'name' => 'Records', 'division_id' => $this->administrative->id,
        ]);
        $records->update(['section_head_employee_id' => $this->headIn($records, 'Recordshead')->id]);

        $this->designate(section: $records);

        $this->assertSame(['Recordshead', 'Adminhead'], $this->chainFor($this->maryJane));
    }

    // -----------------------------------------------------------------
    // And whose roster she is on
    // -----------------------------------------------------------------

    private function rosterOf(Employee $head): string
    {
        $user = User::factory()->create();
        $head->update(['user_id' => $user->id]);

        $html = $this->actingAs($user->fresh())->get('/dashboard')->assertOk()->getContent();
        $start = strpos($html, 'data-head-overview');

        return $start === false ? '' : substr($html, $start);
    }

    public function test_the_division_she_is_posted_to_sees_her(): void
    {
        $this->designate(division: $this->administrative);

        $this->assertStringContainsString(
            'Mary Jane',
            $this->rosterOf($this->administrative->head),
        );
    }

    /** Her old section head no longer has a name they cannot act on. */
    public function test_her_own_section_head_no_longer_does(): void
    {
        $this->designate(division: $this->administrative);

        $this->assertStringNotContainsString('Mary Jane', $this->rosterOf($this->him->fresh()->head));
    }

    public function test_without_a_posting_her_own_section_head_still_does(): void
    {
        $this->assertStringContainsString('Mary Jane', $this->rosterOf($this->him->head));
    }
}
