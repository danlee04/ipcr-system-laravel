<?php

namespace Tests\Feature;

use App\Enums\FunctionCategory;
use App\Enums\IpcrStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrPeriod;
use App\Models\JobFunction;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard answers one question per person: what should I do next?
 *
 * An employee wants their own IPCR's state and the deadline. An approver wants
 * to know how much is waiting. HR and an administrator want to know whether
 * the system is even usable and who has not submitted yet.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function employeeUser(array $employee = []): User
    {
        $user = User::factory()->create();
        Employee::factory()->create(array_merge(['user_id' => $user->id], $employee));

        return $user->fresh();
    }

    private function adminUser(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function openPeriod(array $attributes = []): IpcrPeriod
    {
        return IpcrPeriod::factory()->create(array_merge(['status' => 'open'], $attributes));
    }

    // -----------------------------------------------------------------
    // The employee's own IPCR
    // -----------------------------------------------------------------

    public function test_an_employee_with_no_ipcr_yet_is_told_to_start_one(): void
    {
        $this->openPeriod();

        $this->actingAs($this->employeeUser())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Not started')
            ->assertSee(route('ipcrs.index'), false);
    }

    public function test_an_employee_sees_the_status_of_their_current_ipcr(): void
    {
        $period = $this->openPeriod();
        $user = $this->employeeUser();

        Ipcr::factory()->create([
            'employee_id'    => $user->employee->id,
            'ipcr_period_id' => $period->id,
            'status'         => IpcrStatus::Submitted,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('For Assessment')
            ->assertDontSee('Not started');
    }

    public function test_the_current_period_and_its_deadline_are_shown(): void
    {
        $this->openPeriod([
            'name' => 'January - June 2026', 'submission_deadline' => '2026-07-15',
        ]);

        $this->actingAs($this->employeeUser())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('January - June 2026')
            ->assertSee('15 Jul 2026');
    }

    public function test_an_employee_is_told_when_no_period_is_open(): void
    {
        IpcrPeriod::factory()->closed()->create();

        $this->actingAs($this->employeeUser())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('No rating period is open');
    }

    // -----------------------------------------------------------------
    // The approver
    // -----------------------------------------------------------------

    public function test_an_approver_sees_how_much_is_waiting(): void
    {
        $approver = $this->employeeUser();
        $owner = $this->employeeUser();

        Ipcr::factory()->submitted()->count(2)->create([
            'employee_id'          => $owner->employee->id,
            'assessor_employee_id' => $approver->employee->id,
        ]);

        $this->actingAs($approver)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Waiting for you')
            ->assertSee(route('approvals.inbox'), false);
    }

    public function test_someone_who_approves_nothing_sees_no_approval_card(): void
    {
        $this->actingAs($this->employeeUser())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Waiting for you');
    }

    // -----------------------------------------------------------------
    // HR and the administrator
    // -----------------------------------------------------------------

    public function test_an_admin_is_warned_when_no_chief_of_hospital_is_set(): void
    {
        $this->openPeriod();

        $this->actingAs($this->adminUser())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('No Chief of Hospital');
    }

    public function test_the_warning_disappears_once_a_chief_is_set(): void
    {
        $this->openPeriod();
        Employee::factory()->chiefOfHospital()->create();

        $this->actingAs($this->adminUser())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('No Chief of Hospital');
    }

    public function test_an_admin_is_warned_about_sections_with_no_head(): void
    {
        $this->openPeriod();
        $division = Division::factory()->create();
        Section::factory()->create(['division_id' => $division->id, 'section_head_employee_id' => null]);

        $this->actingAs($this->adminUser())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('without a head');
    }

    /**
     * The dashboard used to warn about common functions with no rating
     * category. There is no such state any more - every function carries a
     * real category - so nothing is left for that check to find.
     */
    public function test_a_function_can_no_longer_be_half_filed(): void
    {
        $this->openPeriod();
        JobFunction::create([
            'category' => FunctionCategory::Support, 'title' => 'Attends meetings', 'is_active' => true,
        ]);

        $this->actingAs($this->adminUser())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('no rating category');
    }

    public function test_an_admin_sees_how_many_employees_have_submitted(): void
    {
        $period = $this->openPeriod();

        $submitted = $this->employeeUser();
        $this->employeeUser();
        $this->employeeUser();

        Ipcr::factory()->submitted()->create([
            'employee_id' => $submitted->employee->id, 'ipcr_period_id' => $period->id,
        ]);

        $this->actingAs($this->adminUser())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('1 of 3');
    }

    /** A draft is not a submission - counting it would overstate progress. */
    public function test_a_draft_does_not_count_as_submitted(): void
    {
        $period = $this->openPeriod();
        $user = $this->employeeUser();

        Ipcr::factory()->create([
            'employee_id' => $user->employee->id, 'ipcr_period_id' => $period->id,
            'status' => IpcrStatus::Draft,
        ]);

        $this->actingAs($this->adminUser())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('0 of 1');
    }

    // -----------------------------------------------------------------
    // Accounts without an employee record
    // -----------------------------------------------------------------

    public function test_an_admin_without_an_employee_record_sees_no_personal_ipcr_card(): void
    {
        $this->openPeriod();
        $admin = $this->adminUser();

        $this->assertNull($admin->employee);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Not started');
    }

    public function test_a_plain_user_with_no_employee_record_still_gets_a_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('no employee record');
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}
