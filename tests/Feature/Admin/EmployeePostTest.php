<?php

namespace Tests\Feature\Admin;

use App\Enums\FunctionCategory;
use App\Enums\IpcrStatus;
use App\Enums\OrgPost;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setting an approving post from the employee form.
 *
 * The post was only settable from the Divisions screen, one page away from
 * the person it describes: you created someone, then went looking for their
 * section to say they led it. Missing that second step left a section with no
 * head, and nobody in it could submit an IPCR at all.
 *
 * Saying it here writes it straight onto the org chart, which is what
 * IpcrRoutingService reads.
 */
class EmployeePostTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private Section $section;

    private Position $position;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->division = Division::factory()->create(['name' => 'Administrative Division']);
        $this->section = Section::factory()->create([
            'division_id' => $this->division->id,
            'name'        => 'HRD Section',
        ]);
        $this->position = Position::factory()->create(['section_id' => $this->section->id]);
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
            'first_name'        => 'Maria',
            'last_name'         => 'Santos',
            'employee_number'   => 'DTRC-3001',
            'employment_status' => 'permanent',
            'division_id'       => $this->division->id,
            'section_id'        => $this->section->id,
            'position_id'       => $this->position->id,
        ], $overrides);
    }

    private function create(array $overrides = []): Employee
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload($overrides))
            ->assertSessionHasNoErrors();

        return Employee::where('employee_number', $this->payload($overrides)['employee_number'])->firstOrFail();
    }

    // -----------------------------------------------------------------
    // Setting a post writes it onto the org chart
    // -----------------------------------------------------------------

    public function test_creating_someone_as_section_head_puts_them_at_the_head_of_their_section(): void
    {
        $employee = $this->create(['post' => OrgPost::SectionHead->value]);

        $this->assertSame($employee->id, $this->section->fresh()->section_head_employee_id);
        $this->assertTrue($employee->isSectionHead());
    }

    public function test_creating_someone_as_division_head_puts_them_at_the_head_of_their_division(): void
    {
        $employee = $this->create([
            'post'       => OrgPost::DivisionHead->value,
            'section_id' => '',
            'position_id' => '',
        ]);

        $this->assertSame($employee->id, $this->division->fresh()->division_head_employee_id);
        $this->assertTrue($employee->isDivisionHead());
    }

    public function test_creating_someone_as_chief_of_hospital_demotes_the_previous_one(): void
    {
        $outgoing = Employee::factory()->chiefOfHospital()->create();

        $incoming = $this->create([
            'post'        => OrgPost::ChiefOfHospital->value,
            'division_id' => '',
            'section_id'  => '',
            'position_id' => '',
        ]);

        $this->assertTrue($incoming->fresh()->is_chief_of_hospital);
        $this->assertFalse($outgoing->fresh()->is_chief_of_hospital, 'There is exactly one Chief of Hospital.');
    }

    public function test_no_post_leaves_the_org_chart_alone(): void
    {
        $this->create();

        $this->assertNull($this->section->fresh()->section_head_employee_id);
        $this->assertNull($this->division->fresh()->division_head_employee_id);
    }

    // -----------------------------------------------------------------
    // A post needs somewhere to be held
    // -----------------------------------------------------------------

    public function test_a_section_head_must_have_a_section(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload([
                'post'        => OrgPost::SectionHead->value,
                'section_id'  => '',
                'position_id' => '',
            ]))
            ->assertSessionHasErrors('post');

        $this->assertNull($this->section->fresh()->section_head_employee_id);
    }

    public function test_a_division_head_must_have_a_division(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload([
                'post'        => OrgPost::DivisionHead->value,
                'division_id' => '',
                'section_id'  => '',
                'position_id' => '',
            ]))
            ->assertSessionHasErrors('post');
    }

    // -----------------------------------------------------------------
    // Standing down
    // -----------------------------------------------------------------

    /**
     * The post is a statement about now, not an addition to a list. Taking it
     * off the form has to take it off the org chart, or a section keeps a head
     * who no longer leads it.
     */
    public function test_clearing_the_post_stands_them_down(): void
    {
        $employee = $this->create(['post' => OrgPost::SectionHead->value]);

        $this->actingAs($this->admin())
            ->put(route('admin.employees.update', $employee), $this->payload(['post' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull($this->section->fresh()->section_head_employee_id);
    }

    public function test_moving_a_head_to_another_section_takes_the_post_with_them(): void
    {
        $employee = $this->create(['post' => OrgPost::SectionHead->value]);

        $newSection = Section::factory()->create(['division_id' => $this->division->id]);

        $this->actingAs($this->admin())
            ->put(route('admin.employees.update', $employee), $this->payload([
                'post'        => OrgPost::SectionHead->value,
                'section_id'  => $newSection->id,
                'position_id' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull($this->section->fresh()->section_head_employee_id, 'The old section must be let go.');
        $this->assertSame($employee->id, $newSection->fresh()->section_head_employee_id);
    }

    public function test_changing_from_section_head_to_division_head_leaves_only_one(): void
    {
        $employee = $this->create(['post' => OrgPost::SectionHead->value]);

        $this->actingAs($this->admin())
            ->put(route('admin.employees.update', $employee), $this->payload([
                'post'        => OrgPost::DivisionHead->value,
                'section_id'  => '',
                'position_id' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull($this->section->fresh()->section_head_employee_id);
        $this->assertSame($employee->id, $this->division->fresh()->division_head_employee_id);
    }

    /** Promoting someone replaces whoever led that section before. */
    public function test_a_new_section_head_replaces_the_old_one(): void
    {
        $first = $this->create(['post' => OrgPost::SectionHead->value]);

        $second = $this->create([
            'employee_number' => 'DTRC-3002',
            'post'            => OrgPost::SectionHead->value,
        ]);

        $this->assertSame($second->id, $this->section->fresh()->section_head_employee_id);
        $this->assertFalse($first->fresh()->isSectionHead());
    }

    // -----------------------------------------------------------------
    // The form
    // -----------------------------------------------------------------

    public function test_the_form_offers_the_three_posts(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="post"', $html);

        foreach (OrgPost::cases() as $post) {
            $this->assertStringContainsString('value="' . $post->value . '"', $html);
            $this->assertStringContainsString($post->label(), $html);
        }
    }

    public function test_the_form_opens_showing_the_post_they_already_hold(): void
    {
        $employee = $this->create(['post' => OrgPost::SectionHead->value]);

        $this->assertSame(OrgPost::SectionHead, OrgPost::heldBy($employee->fresh()));
    }

    /** Who heads what decides where an IPCR goes, so the list has to say. */
    public function test_the_list_shows_the_post_beside_the_name(): void
    {
        $this->create(['post' => OrgPost::SectionHead->value]);

        $this->actingAs($this->admin())
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee('Maria Santos')
            ->assertSee(OrgPost::SectionHead->label());
    }

    // -----------------------------------------------------------------
    // What it is all for
    // -----------------------------------------------------------------

    /**
     * The whole point: set the post on the form, and an IPCR submitted from
     * that section routes to them without anyone touching the org chart.
     */
    public function test_an_ipcr_routes_to_a_head_set_from_the_employee_form(): void
    {
        $head = $this->create(['post' => OrgPost::SectionHead->value]);

        $divisionHead = $this->create([
            'employee_number' => 'DTRC-3003',
            'post'            => OrgPost::DivisionHead->value,
            'section_id'      => '',
            'position_id'     => '',
        ]);

        $staff = Employee::factory()->create([
            'user_id'     => User::factory()->create()->id,
            'section_id'  => $this->section->id,
            'division_id' => $this->division->id,
        ]);

        $ipcr = Ipcr::factory()->create(['employee_id' => $staff->id, 'status' => IpcrStatus::Draft]);
        IpcrItem::factory()->accomplished()->create([
            'ipcr_id' => $ipcr->id, 'category' => FunctionCategory::Core, 'weight' => 100,
        ]);

        $this->actingAs($staff->user)
            ->post(route('ipcrs.submit', $ipcr))
            ->assertSessionMissing('error');

        $ipcr->refresh();

        $this->assertSame($head->id, $ipcr->assessor_employee_id);
        $this->assertSame($divisionHead->id, $ipcr->final_approver_employee_id);
    }
}
