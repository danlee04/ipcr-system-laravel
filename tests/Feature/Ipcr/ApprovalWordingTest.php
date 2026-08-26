<?php

namespace Tests\Feature\Ipcr;

use App\Enums\ApprovalStage;
use App\Enums\FunctionCategory;
use App\Enums\IpcrStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The words the flow is described in.
 *
 * The chain is two posts, not two job descriptions: the Section Head takes an
 * IPCR "For Assessment" and the Division Head gives the "For Final Approval".
 * "Assessor" and "Final Approver" invented a vocabulary the hospital does not
 * use, so the screens name the stage and show the post beside the person.
 */
class ApprovalWordingTest extends TestCase
{
    use RefreshDatabase;

    private Employee $owner;

    private Employee $sectionHead;

    private Employee $divisionHead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $division = Division::factory()->create();
        $section = Section::factory()->create(['division_id' => $division->id]);

        $this->owner = $this->employeeWithLogin(['section_id' => $section->id, 'division_id' => $division->id]);
        $this->sectionHead = $this->employeeWithLogin();
        $this->divisionHead = $this->employeeWithLogin();

        $section->update(['section_head_employee_id' => $this->sectionHead->id]);
        $division->update(['division_head_employee_id' => $this->divisionHead->id]);
    }

    private function employeeWithLogin(array $attributes = []): Employee
    {
        return Employee::factory()->create($attributes + ['user_id' => User::factory()->create()->id]);
    }

    private function routedIpcr(array $overrides = []): Ipcr
    {
        $ipcr = Ipcr::factory()->create($overrides + [
            'employee_id'                => $this->owner->id,
            'assessor_employee_id'       => $this->sectionHead->id,
            'final_approver_employee_id' => $this->divisionHead->id,
            'status'                     => IpcrStatus::Submitted,
            'submitted_at'               => now(),
        ]);

        IpcrItem::factory()->accomplished()->create([
            'ipcr_id'  => $ipcr->id,
            'category' => FunctionCategory::Core,
            'weight'   => 100,
        ]);

        return $ipcr->fresh();
    }

    // -----------------------------------------------------------------
    // The vocabulary itself
    // -----------------------------------------------------------------

    public function test_the_status_after_assessment_is_for_final_approval(): void
    {
        $this->assertSame('For Assessment', IpcrStatus::Submitted->label());
        $this->assertSame('For Final Approval', IpcrStatus::Assessed->label());
    }

    public function test_the_second_stage_is_named_for_final_approval(): void
    {
        $this->assertSame('For Assessment', ApprovalStage::Assessment->label());
        $this->assertSame('For Final Approval', ApprovalStage::FinalRating->label());
    }

    public function test_an_employee_carries_the_post_that_puts_them_in_a_chain(): void
    {
        $this->assertSame('Section Head', $this->sectionHead->postTitle());
        $this->assertSame('Division Head', $this->divisionHead->postTitle());
        $this->assertNull($this->owner->postTitle());

        $chief = Employee::factory()->chiefOfHospital()->create();
        $this->assertSame('Chief of Hospital', $chief->postTitle());
    }

    /** One person can hold two posts; the senior one explains the routing. */
    public function test_the_most_senior_post_wins(): void
    {
        $division = Division::factory()->create(['division_head_employee_id' => $this->sectionHead->id]);
        Section::factory()->create([
            'division_id'              => $division->id,
            'section_head_employee_id' => $this->sectionHead->id,
        ]);

        $this->assertSame('Division Head', $this->sectionHead->fresh()->postTitle());
    }

    public function test_a_name_is_shown_with_its_post(): void
    {
        $this->assertSame(
            $this->sectionHead->full_name . ' — Section Head',
            $this->sectionHead->nameWithPost()
        );

        $this->assertSame($this->owner->full_name, $this->owner->nameWithPost());
    }

    // -----------------------------------------------------------------
    // The screens
    // -----------------------------------------------------------------

    public function test_the_ipcr_page_names_the_two_stages_not_an_assessor(): void
    {
        $ipcr = $this->routedIpcr();

        $this->actingAs($this->owner->user)
            ->get(route('ipcrs.show', $ipcr))
            ->assertOk()
            ->assertSee('For Assessment')
            ->assertSee('For Final Approval')
            ->assertDontSee('Assessor')
            ->assertDontSee('Final Approver');
    }

    public function test_the_ipcr_page_shows_the_post_beside_each_approver(): void
    {
        $ipcr = $this->routedIpcr();

        $this->actingAs($this->owner->user)
            ->get(route('ipcrs.show', $ipcr))
            ->assertOk()
            ->assertSee($this->sectionHead->full_name . ' — Section Head')
            ->assertSee($this->divisionHead->full_name . ' — Division Head');
    }

    public function test_the_approval_inbox_does_not_say_assessor(): void
    {
        $this->routedIpcr();

        $this->actingAs($this->sectionHead->user)
            ->get(route('approvals.inbox'))
            ->assertOk()
            ->assertSee('For Assessment')
            ->assertDontSee('assessor');
    }

    public function test_the_admin_list_does_not_say_assessor(): void
    {
        $this->routedIpcr();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.ipcrs.index'))
            ->assertOk()
            ->assertSee('For Assessment')
            ->assertDontSee('Assessor');
    }

    public function test_the_printed_sheet_names_the_stages(): void
    {
        $ipcr = $this->routedIpcr(['status' => IpcrStatus::Approved, 'approved_at' => now()]);

        $this->actingAs($this->owner->user)
            ->get(route('ipcrs.print', $ipcr))
            ->assertOk()
            ->assertDontSee('Assessor');
    }
}
