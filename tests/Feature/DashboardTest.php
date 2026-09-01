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

    /**
     * The way into the IPCR list sits at the end of the filter row.
     *
     * It used to be on a line of its own above the three selects, which put a
     * button in the middle of the controls it carries the answers from - the
     * period, division and section it links with are all chosen underneath it.
     */
    /**
     * A chart can never paint over the panel beside it.
     *
     * Chart.js writes the canvas size into an inline style, and a page
     * restored with the browser's Back button keeps that style while the
     * column around it is laid out again from scratch - so a chart drawn at
     * one width can find itself in a narrower one and spill into the rail.
     * The redraw on restore is in app.js; this is the box that makes the
     * spill impossible whatever goes wrong inside it.
     */
    public function test_a_chart_cannot_spill_out_of_its_box(): void
    {
        $period = $this->openPeriod();
        Ipcr::factory()->create([
            'employee_id'    => Employee::factory()->create()->id,
            'ipcr_period_id' => $period->id,
            'status'         => IpcrStatus::Approved,
        ]);

        $html = $this->actingAs($this->adminUser())->get('/dashboard')->assertOk()->getContent();

        preg_match_all('#<div class="([^"]*)">\s*<canvas data-chart#s', $html, $boxes);

        $this->assertNotEmpty($boxes[1], 'No chart on the page, so nothing was checked.');

        foreach ($boxes[1] as $classes) {
            $this->assertStringContainsString('overflow-hidden', $classes);
        }
    }

    /**
     * The doughnut is boxed to a square rather than given the card.
     *
     * It fills the smaller side of whatever it is handed, so in a wide box it
     * is a small ring in a field of nothing - and its size tracks the column,
     * which is what let a canvas restored from the browser cache sit at the
     * wrong width and spill over the panel beside it. A fixed square cannot
     * do either.
     */
    public function test_the_doughnut_is_boxed_to_a_square(): void
    {
        $period = $this->openPeriod();
        Ipcr::factory()->create([
            'employee_id'    => Employee::factory()->create()->id,
            'ipcr_period_id' => $period->id,
            'status'         => IpcrStatus::Approved,
        ]);

        $html = $this->actingAs($this->adminUser())->get('/dashboard')->assertOk()->getContent();

        $this->assertSame(
            1,
            preg_match('#<div class="([^"]*)">\s*<canvas data-chart="doughnut"#s', $html, $box),
            'No doughnut on the page.',
        );

        $this->assertMatchesRegularExpression('/\bh-\d+\b/', $box[1], 'The ring has no fixed height.');
        $this->assertMatchesRegularExpression('/\bw-\d+\b/', $box[1], 'The ring stretches with the column.');
    }

    public function test_the_link_to_the_ipcr_list_follows_the_filters(): void
    {
        $html = $this->actingAs($this->adminUser())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Manage IPCRs', $html);
        $this->assertStringNotContainsString('Open IPCRs', $html);

        $this->assertLessThan(
            strpos($html, 'Manage IPCRs'),
            strpos($html, 'name="filter_section_id"'),
            'The button still comes before the section filter.',
        );
    }

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

    /**
     * The bare "1 of 3" card is gone.
     *
     * It said how many had submitted and nothing about who, so the only thing
     * to do with it was go and look somewhere else. The Period Summary answers
     * the same question by name, division and section.
     */
    public function test_the_submission_count_card_is_gone(): void
    {
        $period = $this->openPeriod();

        $submitted = $this->employeeUser();
        $this->employeeUser();

        Ipcr::factory()->submitted()->create([
            'employee_id' => $submitted->employee->id, 'ipcr_period_id' => $period->id,
        ]);

        $this->actingAs($this->adminUser())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Submitted this period')
            ->assertDontSee('1 of 2');
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

    /**
     * And is not told about it.
     *
     * The card used to explain that this account has no employee record and
     * that this is normal for an administrator. Whoever set the account up
     * already knows, and it took the widest slot on the page to say it every
     * single visit.
     */
    public function test_a_plain_user_with_no_employee_record_still_gets_a_page(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Ana Cruz']))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Ana')
            ->assertDontSee('no employee record');
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}
