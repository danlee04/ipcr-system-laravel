<?php

namespace Tests\Feature\Admin;

use App\Enums\OrgPost;
use App\Models\Designation;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The person whose plantilla is in one place and whose work is in another.
 *
 * The real case at DTRC: an Administrative Officer on the Health Information
 * Management Section plantilla, designated OIC - Human Resource Management
 * Officer, and Section Head of the HRD Section.
 *
 * Three separate facts about one person, and the form used to collapse them
 * into one:
 *
 *   plantilla position -> where the item number sits, and where CORE
 *                         functions come from
 *   designation        -> the post they actually perform, wherever it sits
 *   approving post     -> the section or division they lead, which need not
 *                         be the one they sit in
 */
class SplitPlacementTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private Section $him;

    private Section $hrd;

    private Position $plantilla;

    private Designation $oic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->division = Division::factory()->create(['name' => 'Administrative Division']);
        $this->him = Section::factory()->create([
            'division_id' => $this->division->id,
            'name'        => 'Health Information Management Section',
        ]);
        $this->hrd = Section::factory()->create([
            'division_id' => $this->division->id,
            'name'        => 'HRD Section',
        ]);

        $this->plantilla = Position::factory()->create([
            'section_id' => $this->him->id,
            'title'      => 'Administrative Officer III',
        ]);

        $this->oic = Designation::factory()->create([
            'title' => 'OIC - Human Resource Management Officer',
        ]);
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
            'first_name'        => 'Elena',
            'last_name'         => 'Reyes',
            'employee_number'   => 'DTRC-4001',
            'employment_status' => 'permanent',
            'division_id'       => $this->division->id,
            'section_id'        => $this->him->id,
            'position_id'       => $this->plantilla->id,
        ], $overrides);
    }

    private function create(array $overrides = []): Employee
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload($overrides))
            ->assertSessionHasNoErrors();

        return Employee::where('employee_number', 'DTRC-4001')->firstOrFail();
    }

    // -----------------------------------------------------------------
    // Leading a section other than your own
    // -----------------------------------------------------------------

    /**
     * The headship goes where it is aimed, not where the employee sits.
     *
     * Writing it onto their own section put the wrong person at the head of
     * Health Information Management and left HRD with none - so nobody in
     * either section could submit an IPCR correctly.
     */
    public function test_a_section_head_can_lead_a_section_they_do_not_sit_in(): void
    {
        $employee = $this->create([
            'post'             => OrgPost::SectionHead->value,
            'heads_section_id' => $this->hrd->id,
        ]);

        $this->assertSame($employee->id, $this->hrd->fresh()->section_head_employee_id);
        $this->assertNull(
            $this->him->fresh()->section_head_employee_id,
            'The section they merely sit in must not be handed a head.'
        );
    }

    /** Left blank, it still means the section they sit in. */
    public function test_the_section_they_sit_in_is_the_default(): void
    {
        $employee = $this->create(['post' => OrgPost::SectionHead->value]);

        $this->assertSame($employee->id, $this->him->fresh()->section_head_employee_id);
    }

    public function test_moving_the_headship_releases_the_section_they_used_to_lead(): void
    {
        $employee = $this->create([
            'post'             => OrgPost::SectionHead->value,
            'heads_section_id' => $this->hrd->id,
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.employees.update', $employee), $this->payload([
                'post'             => OrgPost::SectionHead->value,
                'heads_section_id' => $this->him->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull($this->hrd->fresh()->section_head_employee_id);
        $this->assertSame($employee->id, $this->him->fresh()->section_head_employee_id);
    }

    /** The same split one level up. */
    public function test_a_division_head_can_lead_a_division_they_do_not_sit_in(): void
    {
        $other = Division::factory()->create(['name' => 'Medical Services Division']);

        $employee = $this->create([
            'post'              => OrgPost::DivisionHead->value,
            'heads_division_id' => $other->id,
        ]);

        $this->assertSame($employee->id, $other->fresh()->division_head_employee_id);
        $this->assertNull($this->division->fresh()->division_head_employee_id);
    }

    public function test_the_section_led_must_actually_exist(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload([
                'post'             => OrgPost::SectionHead->value,
                'heads_section_id' => 9999,
            ]))
            ->assertSessionHasErrors('heads_section_id');
    }

    // -----------------------------------------------------------------
    // The designation, which had no way in at all
    // -----------------------------------------------------------------

    /**
     * Nothing in the app attached a designation to anybody. The pivot table
     * existed, FunctionCatalogService read it, and the only row in it came
     * from the seeder - so no strategic or support function could ever reach
     * a real employee.
     */
    public function test_a_designation_can_be_given_on_the_employee_form(): void
    {
        $employee = $this->create(['designations' => [$this->oic->id]]);

        $this->assertTrue($employee->activeDesignations->contains($this->oic));
    }

    public function test_more_than_one_designation_can_be_held_at_once(): void
    {
        $second = Designation::factory()->create(['title' => 'OIC - Budget Officer']);

        $employee = $this->create(['designations' => [$this->oic->id, $second->id]]);

        $this->assertCount(2, $employee->activeDesignations);
    }

    public function test_removing_a_designation_takes_it_away(): void
    {
        $employee = $this->create(['designations' => [$this->oic->id]]);

        $this->actingAs($this->admin())
            ->put(route('admin.employees.update', $employee), $this->payload(['designations' => []]))
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $employee->fresh()->activeDesignations);
    }

    public function test_an_unknown_designation_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload(['designations' => [9999]]))
            ->assertSessionHasErrors('designations.0');
    }

    public function test_the_form_offers_the_designations(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="designations[]"', $html);
        $this->assertStringContainsString('OIC - Human Resource Management Officer', $html);
    }

    // -----------------------------------------------------------------
    // All three at once - the whole case
    // -----------------------------------------------------------------

    public function test_the_whole_case_holds_together(): void
    {
        $employee = $this->create([
            'post'             => OrgPost::SectionHead->value,
            'heads_section_id' => $this->hrd->id,
            'designations'     => [$this->oic->id],
        ]);

        // Plantilla stays where the item number is.
        $this->assertSame($this->plantilla->id, $employee->position_id);
        $this->assertSame($this->him->id, $employee->section_id);

        // The work, and the section led, are both elsewhere.
        $this->assertTrue($employee->activeDesignations->contains($this->oic));
        $this->assertSame($employee->id, $this->hrd->fresh()->section_head_employee_id);
        $this->assertSame('Section Head', $employee->postTitle());
    }
}
