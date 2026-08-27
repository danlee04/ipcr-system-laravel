<?php

namespace Tests\Feature\Admin;

use App\Enums\IpcrStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrPeriod;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What HR has to hand in at the end of a rating period.
 *
 * Not a list of IPCRs - a roll of the hospital. Every active employee appears,
 * whether or not they ever started one, because the people who never started
 * are exactly who HR is looking for. Beside each name is the state their IPCR
 * reached and the rating it earned, gathered into divisions and sections so
 * the sheet can be handed to the PMT as it stands.
 */
class PeriodSummaryTest extends TestCase
{
    use RefreshDatabase;

    private IpcrPeriod $period;

    private Division $division;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->period = IpcrPeriod::factory()->create(['status' => 'open', 'name' => 'January - June 2026']);
        $this->division = Division::factory()->create(['name' => 'Administrative Division']);
        $this->section = Section::factory()->create([
            'division_id' => $this->division->id, 'name' => 'Human Resource Development Section',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function employee(array $attributes = []): Employee
    {
        return Employee::factory()->create(array_merge([
            'division_id' => $this->division->id,
            'section_id'  => $this->section->id,
        ], $attributes));
    }

    private function ipcrFor(Employee $employee, IpcrStatus $status, ?float $rating = null, ?string $adjectival = null): Ipcr
    {
        return Ipcr::factory()->create([
            'employee_id'             => $employee->id,
            'ipcr_period_id'          => $this->period->id,
            'status'                  => $status,
            'submitted_at'            => $status === IpcrStatus::Draft ? null : now(),
            'approved_at'             => $status === IpcrStatus::Approved ? now() : null,
            'final_numerical_rating'  => $rating,
            'final_adjectival_rating' => $adjectival,
        ]);
    }

    private function summary(array $query = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.summary.index', $query));
    }

    // -----------------------------------------------------------------
    // Who may look
    // -----------------------------------------------------------------

    public function test_hr_may_open_it(): void
    {
        $this->actingAs($this->userWithRole('hr'))
            ->get(route('admin.summary.index'))
            ->assertOk();
    }

    public function test_an_ordinary_employee_may_not(): void
    {
        $user = User::factory()->create();
        $user->assignRole('employee');
        Employee::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('admin.summary.index'))->assertForbidden();
    }

    // -----------------------------------------------------------------
    // The roll
    // -----------------------------------------------------------------

    public function test_it_opens_on_the_active_period(): void
    {
        IpcrPeriod::factory()->closed()->create(['name' => 'July - December 2025']);

        $this->summary()->assertOk()->assertSee('January - June 2026');
    }

    /** The point of the sheet: the people who never started are on it. */
    public function test_somebody_who_never_started_is_still_on_the_sheet(): void
    {
        $this->employee(['first_name' => 'Maria', 'last_name' => 'Santos']);

        $this->summary()->assertOk()
            ->assertSee('Maria Santos')
            ->assertSee('Not started');
    }

    public function test_a_retired_employee_is_not_expected_to_have_one(): void
    {
        $this->employee(['first_name' => 'Jose', 'last_name' => 'Rizal', 'is_active' => false]);

        $this->summary()->assertOk()->assertDontSee('Jose Rizal');
    }

    public function test_an_ipcr_from_another_period_does_not_count_as_this_one(): void
    {
        $employee = $this->employee();
        Ipcr::factory()->create([
            'employee_id'    => $employee->id,
            'ipcr_period_id' => IpcrPeriod::factory()->create()->id,
            'status'         => IpcrStatus::Approved,
        ]);

        $this->summary(['period' => $this->period->id])->assertOk()->assertSee('Not started');
    }

    public function test_the_rating_is_shown_beside_the_name(): void
    {
        $this->ipcrFor($this->employee(), IpcrStatus::Approved, 4.567, 'Very Satisfactory');

        $this->summary()->assertOk()
            ->assertSee('4.57')
            ->assertSee('Very Satisfactory');
    }

    // -----------------------------------------------------------------
    // The tally
    // -----------------------------------------------------------------

    public function test_it_counts_how_many_started_submitted_and_finished(): void
    {
        $this->employee();                                              // never started
        $this->ipcrFor($this->employee(), IpcrStatus::Draft);           // started only
        $this->ipcrFor($this->employee(), IpcrStatus::Submitted);       // submitted
        $this->ipcrFor($this->employee(), IpcrStatus::Approved, 4.0);   // finished

        $this->summary()->assertOk()
            ->assertSee('4 employees')
            ->assertSee('2 submitted')
            ->assertSee('1 approved');
    }

    /**
     * Only approved IPCRs are averaged. A rating that has not been signed off
     * can still change, and an average that moves under HR is worse than none.
     */
    public function test_the_average_counts_approved_ratings_only(): void
    {
        $this->ipcrFor($this->employee(), IpcrStatus::Approved, 5.0);
        $this->ipcrFor($this->employee(), IpcrStatus::Assessed, 1.0);

        $this->summary()->assertOk()
            ->assertSee('Average 5.00')
            ->assertDontSee('1.00', 'An unapproved rating is not reported at all, averaged or otherwise.');
    }

    public function test_a_section_with_nobody_approved_yet_shows_no_average(): void
    {
        $this->ipcrFor($this->employee(), IpcrStatus::Submitted);

        $this->summary()->assertOk()->assertDontSee('0.00');
    }

    // -----------------------------------------------------------------
    // Gathered into divisions and sections
    // -----------------------------------------------------------------

    public function test_the_roll_is_gathered_by_division_and_section(): void
    {
        $this->employee();

        $this->summary()->assertOk()
            ->assertSee('Administrative Division')
            ->assertSee('Human Resource Development Section');
    }

    public function test_one_division_can_be_asked_for_on_its_own(): void
    {
        $other = Division::factory()->create(['name' => 'Medical Division']);
        $this->employee(['first_name' => 'Ana', 'last_name' => 'Cruz']);
        $this->employee(['first_name' => 'Ben', 'last_name' => 'Dela Cruz', 'division_id' => $other->id, 'section_id' => null]);

        $this->summary(['division' => $this->division->id])->assertOk()
            ->assertSee('Ana Cruz')
            ->assertDontSee('Ben Dela Cruz');
    }

    /** Somebody assigned to no section still belongs to their division. */
    public function test_somebody_with_no_section_is_not_lost(): void
    {
        $this->employee(['first_name' => 'Pedro', 'last_name' => 'Reyes', 'section_id' => null]);

        $this->summary()->assertOk()->assertSee('Pedro Reyes');
    }

    // -----------------------------------------------------------------
    // The download
    // -----------------------------------------------------------------

    public function test_the_sheet_can_be_downloaded(): void
    {
        $this->ipcrFor(
            $this->employee(['first_name' => 'Maria', 'last_name' => 'Santos']),
            IpcrStatus::Approved,
            4.5,
            'Very Satisfactory'
        );

        $response = $this->actingAs($this->userWithRole('hr'))
            ->get(route('admin.summary.export', ['period' => $this->period->id]));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Division,Section', $csv);
        $this->assertStringContainsString('Maria', $csv);
        $this->assertStringContainsString('Very Satisfactory', $csv);
    }

    public function test_the_download_is_named_after_the_period(): void
    {
        $this->employee();

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('admin.summary.export', ['period' => $this->period->id]))
            ->assertDownload('ipcr-summary-january-june-2026.csv');
    }

    public function test_the_download_carries_the_same_filters(): void
    {
        $other = Division::factory()->create(['name' => 'Medical Division']);
        $this->employee(['first_name' => 'Ana', 'last_name' => 'Cruz']);
        $this->employee(['first_name' => 'Ben', 'last_name' => 'Dela Cruz', 'division_id' => $other->id, 'section_id' => null]);

        $csv = $this->actingAs($this->userWithRole('hr'))
            ->get(route('admin.summary.export', [
                'period' => $this->period->id, 'division' => $this->division->id,
            ]))
            ->streamedContent();

        $this->assertStringContainsString('Ana', $csv);
        $this->assertStringNotContainsString('Ben', $csv);
    }

    public function test_an_ordinary_employee_cannot_download_it(): void
    {
        $user = User::factory()->create();
        $user->assignRole('employee');
        Employee::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('admin.summary.export'))->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Nothing to report on
    // -----------------------------------------------------------------

    public function test_it_says_so_when_no_period_exists_at_all(): void
    {
        IpcrPeriod::query()->delete();

        $this->summary()->assertOk()->assertSee('No rating period');
    }
}
