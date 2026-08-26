<?php

namespace Tests\Feature\Ipcr;

use App\Enums\FunctionCategory;
use App\Enums\IpcrStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nobody chooses an approver. Submitting works it out from the org chart.
 *
 * The only thing HR ever sets is who holds each post - the Section Head on a
 * section, the Division Head on a division. From there every IPCR routes
 * itself:
 *
 *   employee      -> their Section Head assesses, that section's Division
 *                    Head gives the final approval
 *   Section Head  -> their Division Head assesses, the Chief of Hospital
 *                    gives the final approval
 *   Division Head -> the Chief of Hospital does both
 *
 * These tests exist because that rule had none. It is the single most
 * important behaviour in the system and it was being taken on trust.
 */
class AutomaticRoutingTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private Section $section;

    private Employee $sectionHead;

    private Employee $divisionHead;

    private Employee $chief;

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = Division::factory()->create();
        $this->section = Section::factory()->create(['division_id' => $this->division->id]);

        $this->sectionHead = $this->employeeIn($this->section);
        $this->divisionHead = $this->employeeIn($this->section);
        $this->chief = Employee::factory()->chiefOfHospital()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->section->update(['section_head_employee_id' => $this->sectionHead->id]);
        $this->division->update(['division_head_employee_id' => $this->divisionHead->id]);
    }

    private function employeeIn(?Section $section): Employee
    {
        return Employee::factory()->create([
            'user_id'     => User::factory()->create()->id,
            'section_id'  => $section?->id,
            'division_id' => $section?->division_id,
        ]);
    }

    /** A draft that will pass every submit guard, so only routing is on trial. */
    private function submittableDraft(Employee $owner): Ipcr
    {
        $ipcr = Ipcr::factory()->create([
            'employee_id' => $owner->id,
            'status'      => IpcrStatus::Draft,
        ]);

        IpcrItem::factory()->accomplished()->create([
            'ipcr_id'  => $ipcr->id,
            'category' => FunctionCategory::Core,
            'weight'   => 100,
        ]);

        return $ipcr->fresh();
    }

    private function submit(Employee $owner): Ipcr
    {
        $ipcr = $this->submittableDraft($owner);

        $this->actingAs($owner->user)
            ->post(route('ipcrs.submit', $ipcr))
            ->assertSessionMissing('error');

        return $ipcr->fresh();
    }

    // -----------------------------------------------------------------
    // The three shapes of the chain
    // -----------------------------------------------------------------

    public function test_an_employee_is_routed_to_their_section_head_then_their_division_head(): void
    {
        $ipcr = $this->submit($this->employeeIn($this->section));

        $this->assertSame(IpcrStatus::Submitted, $ipcr->status);
        $this->assertSame($this->sectionHead->id, $ipcr->assessor_employee_id);
        $this->assertSame($this->divisionHead->id, $ipcr->final_approver_employee_id);
    }

    public function test_a_section_head_is_routed_to_their_division_head_then_the_chief(): void
    {
        $ipcr = $this->submit($this->sectionHead);

        $this->assertSame($this->divisionHead->id, $ipcr->assessor_employee_id);
        $this->assertSame($this->chief->id, $ipcr->final_approver_employee_id);
    }

    public function test_a_division_head_is_routed_to_the_chief_for_both_steps(): void
    {
        $ipcr = $this->submit($this->divisionHead);

        $this->assertSame($this->chief->id, $ipcr->assessor_employee_id);
        $this->assertSame($this->chief->id, $ipcr->final_approver_employee_id);
    }

    /**
     * A Division Head may still carry the section_id they held before being
     * promoted. The post has to win, or they would be routed to the Section
     * Head who now reports to them.
     */
    public function test_the_post_wins_over_a_section_left_over_from_before(): void
    {
        $ipcr = $this->submit($this->divisionHead);

        $this->assertNotSame($this->sectionHead->id, $ipcr->assessor_employee_id);
    }

    public function test_a_head_of_another_section_is_never_used(): void
    {
        $otherSection = Section::factory()->create(['division_id' => $this->division->id]);
        $otherHead = $this->employeeIn($otherSection);
        $otherSection->update(['section_head_employee_id' => $otherHead->id]);

        $ipcr = $this->submit($this->employeeIn($this->section));

        $this->assertSame($this->sectionHead->id, $ipcr->assessor_employee_id);
    }

    // -----------------------------------------------------------------
    // Nobody sets it by hand
    // -----------------------------------------------------------------

    /** No administrator touched this IPCR, and it still found its chain. */
    public function test_no_administrative_action_is_involved(): void
    {
        $ipcr = $this->submit($this->employeeIn($this->section));

        $this->assertNull($ipcr->chain_overridden_at);
        $this->assertFalse($ipcr->hasOverriddenChain());
        $this->assertSame(0, $ipcr->approvals()->count());
    }

    /**
     * Resubmission resolves the chain again rather than reusing the stamp, so
     * a change of Section Head is picked up by the IPCRs still in flight.
     */
    public function test_a_new_section_head_takes_over_on_resubmission(): void
    {
        $employee = $this->employeeIn($this->section);
        $ipcr = $this->submit($employee);

        $this->assertSame($this->sectionHead->id, $ipcr->assessor_employee_id);

        $successor = $this->employeeIn($this->section);
        $this->section->update(['section_head_employee_id' => $successor->id]);

        $ipcr->update(['status' => IpcrStatus::Returned]);
        $this->actingAs($employee->user)->post(route('ipcrs.submit', $ipcr));

        $this->assertSame($successor->id, $ipcr->fresh()->assessor_employee_id);
    }

    // -----------------------------------------------------------------
    // Where it cannot decide
    // -----------------------------------------------------------------

    public function test_an_employee_with_no_section_head_is_told_why(): void
    {
        $headless = Section::factory()->create(['division_id' => $this->division->id]);
        $employee = $this->employeeIn($headless);
        $ipcr = $this->submittableDraft($employee);

        $this->actingAs($employee->user)
            ->post(route('ipcrs.submit', $ipcr))
            ->assertSessionHas('error');

        $this->assertSame(IpcrStatus::Draft, $ipcr->fresh()->status);
    }

    public function test_the_chief_of_hospital_has_no_automatic_chain(): void
    {
        $ipcr = $this->submittableDraft($this->chief);

        $this->actingAs($this->chief->user)
            ->post(route('ipcrs.submit', $ipcr))
            ->assertSessionHas('error');

        $this->assertSame(IpcrStatus::Draft, $ipcr->fresh()->status);
    }
}
