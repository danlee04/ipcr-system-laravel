<?php

namespace Tests\Feature\Ipcr;

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
use App\Services\IpcrRoutingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A head whose plantilla sits under a different division entirely.
 *
 * The case at DTRC: the Section Head of HRDS is on the HIMS Section
 * plantilla, and HIMS sits under RITD - a different division from the one
 * HRDS belongs to.
 *
 * Two things have to follow from that, and they pull in opposite directions:
 *
 *   everyone in HRDS  -> assessed by this head, then by the Administrative
 *                        Division Head, because that is the division HRDS
 *                        belongs to
 *   the head's own    -> assessed by the Administrative Division Head too -
 *                        the division of the section they LEAD, never the
 *                        division their plantilla happens to sit in
 *
 * RITD must not appear anywhere in either chain.
 */
class CrossDivisionHeadTest extends TestCase
{
    use RefreshDatabase;

    private Division $administrative;

    private Division $ritd;

    private Section $hrds;

    private Section $hims;

    private Employee $head;

    private Employee $administrativeHead;

    private Employee $ritdHead;

    private Employee $chief;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        // One open period for everybody: an IPCR is created per employee per
        // period, and store() picks the open one.
        \App\Models\IpcrPeriod::factory()->create();

        $this->administrative = Division::factory()->create(['name' => 'Administrative Division']);
        $this->ritd = Division::factory()->create(['name' => 'RITD Division']);

        $this->hrds = Section::factory()->create([
            'division_id' => $this->administrative->id,
            'name'        => 'HRDS',
        ]);
        $this->hims = Section::factory()->create([
            'division_id' => $this->ritd->id,
            'name'        => 'HIMS',
        ]);

        $this->administrativeHead = $this->employeeIn($this->administrative, null);
        $this->ritdHead = $this->employeeIn($this->ritd, null);
        $this->chief = Employee::factory()->chiefOfHospital()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->administrative->update(['division_head_employee_id' => $this->administrativeHead->id]);
        $this->ritd->update(['division_head_employee_id' => $this->ritdHead->id]);

        // The head themselves: plantilla in HIMS under RITD, leading HRDS.
        $this->head = $this->createThroughTheForm();
    }

    private function employeeIn(Division $division, ?Section $section): Employee
    {
        return Employee::factory()->create([
            'user_id'     => User::factory()->create()->id,
            'division_id' => $division->id,
            'section_id'  => $section?->id,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /** Built the way HR would build it, not by writing the columns directly. */
    private function createThroughTheForm(): Employee
    {
        $position = Position::factory()->create([
            'section_id' => $this->hims->id,
            'title'      => 'Administrative Officer III',
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), [
                'first_name'        => 'Elena',
                'last_name'         => 'Reyes',
                'employee_number'   => 'DTRC-5001',
                'email'             => 'elena@dtrc.test',
                'employment_status' => 'permanent',
                'division_id'       => $this->ritd->id,
                'section_id'        => $this->hims->id,
                'position_id'       => $position->id,
                'post'              => OrgPost::SectionHead->value,
                'heads_section_id'  => $this->hrds->id,
            ])
            ->assertSessionHasNoErrors();

        return Employee::where('employee_number', 'DTRC-5001')->firstOrFail();
    }

    /**
     * Created through the controller, not the factory: the office stamped on
     * the sheet is written by store(), so a factory-built IPCR would never
     * exercise it.
     */
    private function submit(Employee $owner): Ipcr
    {
        $this->actingAs($owner->user)
            ->post(route('ipcrs.store'), ['mode' => 'targets_only'])
            ->assertSessionMissing('error');

        $ipcr = $owner->ipcrs()->latest('id')->firstOrFail();

        IpcrItem::factory()->accomplished()->rated()->create([
            'ipcr_id' => $ipcr->id, 'category' => FunctionCategory::Core, 'weight' => 100,
        ]);

        $this->actingAs($owner->user)
            ->post(route('ipcrs.submit', $ipcr))
            ->assertSessionMissing('error');

        return $ipcr->fresh();
    }

    // -----------------------------------------------------------------
    // Setting it up at all
    // -----------------------------------------------------------------

    public function test_the_head_leads_hrds_while_sitting_in_hims(): void
    {
        $this->assertSame($this->head->id, $this->hrds->fresh()->section_head_employee_id);
        $this->assertSame($this->hims->id, $this->head->section_id);
        $this->assertNull($this->hims->fresh()->section_head_employee_id);
    }

    // -----------------------------------------------------------------
    // Everyone in HRDS
    // -----------------------------------------------------------------

    public function test_everyone_in_hrds_is_assessed_by_that_head(): void
    {
        $staff = $this->employeeIn($this->administrative, $this->hrds);

        $ipcr = $this->submit($staff);

        $this->assertSame(IpcrStatus::Submitted, $ipcr->status);
        $this->assertSame($this->head->id, $ipcr->assessor_employee_id);
    }

    public function test_hrds_work_is_finally_approved_by_the_administrative_division_head(): void
    {
        $ipcr = $this->submit($this->employeeIn($this->administrative, $this->hrds));

        $this->assertSame($this->administrativeHead->id, $ipcr->final_approver_employee_id);
        $this->assertNotSame($this->ritdHead->id, $ipcr->final_approver_employee_id);
    }

    /** Two people in HRDS, one chain. */
    public function test_it_holds_for_more_than_one_of_them(): void
    {
        foreach ([1, 2] as $ignored) {
            $ipcr = $this->submit($this->employeeIn($this->administrative, $this->hrds));

            $this->assertSame($this->head->id, $ipcr->assessor_employee_id);
        }
    }

    // -----------------------------------------------------------------
    // The head's own IPCR
    // -----------------------------------------------------------------

    /**
     * The division of the section they LEAD, not of the plantilla they sit on.
     * Reading it off their own record would send this IPCR to RITD, whose head
     * has no standing over HRDS at all.
     */
    public function test_the_heads_own_ipcr_goes_to_the_administrative_division(): void
    {
        $ipcr = $this->submit($this->head);

        $this->assertSame($this->administrativeHead->id, $ipcr->assessor_employee_id);
        $this->assertSame($this->chief->id, $ipcr->final_approver_employee_id);
    }

    public function test_the_ritd_head_is_nowhere_in_either_chain(): void
    {
        $own = $this->submit($this->head);
        $staff = $this->submit($this->employeeIn($this->administrative, $this->hrds));

        foreach ([$own, $staff] as $ipcr) {
            $this->assertNotSame($this->ritdHead->id, $ipcr->assessor_employee_id);
            $this->assertNotSame($this->ritdHead->id, $ipcr->final_approver_employee_id);
        }
    }

    /** Asked of the routing directly, with no IPCR in the way. */
    public function test_the_resolver_agrees(): void
    {
        $chain = app(IpcrRoutingService::class)->resolve($this->head->fresh());

        $this->assertSame($this->administrativeHead->id, $chain->assessor->id);
        $this->assertSame($this->chief->id, $chain->finalApprover->id);
    }

    // -----------------------------------------------------------------
    // What the printed form says
    // -----------------------------------------------------------------

    /**
     * The office on the sheet is where the work is done.
     *
     * Printing HIMS would put it at odds with the signature block right below
     * it, which names the Administrative Division Head - one document making
     * two claims about which division this person answers to.
     */
    public function test_the_printed_office_is_the_section_they_lead(): void
    {
        $ipcr = $this->submit($this->head);

        $this->assertSame('HRDS', $ipcr->office_name);
    }

    /** For everyone else it is still simply where they sit. */
    public function test_an_ordinary_employees_office_is_their_own_section(): void
    {
        $ipcr = $this->submit($this->employeeIn($this->administrative, $this->hrds));

        $this->assertSame('HRDS', $ipcr->office_name);
    }

    // -----------------------------------------------------------------
    // The inbox they work from
    // -----------------------------------------------------------------

    public function test_the_hrds_work_lands_in_their_inbox(): void
    {
        $staff = $this->employeeIn($this->administrative, $this->hrds);
        $this->submit($staff);

        $this->actingAs($this->head->user)
            ->get(route('approvals.inbox'))
            ->assertOk()
            ->assertSee($staff->full_name);
    }
}
