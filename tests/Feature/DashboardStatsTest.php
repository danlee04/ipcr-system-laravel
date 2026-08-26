<?php

namespace Tests\Feature;

use App\Enums\IpcrStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrPeriod;
use App\Models\Section;
use App\Services\DashboardStats;
use App\Support\DashboardScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The numbers behind the admin dashboard.
 *
 * Every figure is filterable by period, division and section, so the same
 * service answers "how is the whole hospital doing" and "how is Nursing doing
 * this semester" without a second code path.
 */
class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    private function stats(): DashboardStats
    {
        return app(DashboardStats::class);
    }

    private function ipcrFor(Employee $employee, IpcrPeriod $period, IpcrStatus $status, ?float $rating = null): Ipcr
    {
        return Ipcr::factory()->create([
            'employee_id'            => $employee->id,
            'ipcr_period_id'         => $period->id,
            'status'                 => $status,
            'final_numerical_rating' => $rating,
        ]);
    }

    // -----------------------------------------------------------------
    // Headline counts
    // -----------------------------------------------------------------

    public function test_it_counts_each_status_separately(): void
    {
        $period = IpcrPeriod::factory()->create();

        // One employee each: ipcrs is unique on (employee_id, ipcr_period_id).
        foreach ([IpcrStatus::Draft, IpcrStatus::Submitted, IpcrStatus::Assessed, IpcrStatus::Approved, IpcrStatus::Returned] as $status) {
            $this->ipcrFor(Employee::factory()->create(), $period, $status);
        }

        $totals = $this->stats()->totals(new DashboardScope());

        $this->assertSame(5, $totals['total']);
        $this->assertSame(1, $totals['draft']);
        $this->assertSame(1, $totals['review']);
        $this->assertSame(1, $totals['final']);
        $this->assertSame(1, $totals['approved']);
        $this->assertSame(1, $totals['returned']);
    }

    /**
     * The old dashboard labelled its "For Final Rating" card with the returned
     * count. They are different states here and must not be conflated.
     */
    public function test_for_final_rating_counts_assessed_not_returned(): void
    {
        $period = IpcrPeriod::factory()->create();

        $this->ipcrFor(Employee::factory()->create(), $period, IpcrStatus::Assessed);
        $this->ipcrFor(Employee::factory()->create(), $period, IpcrStatus::Returned);
        $this->ipcrFor(Employee::factory()->create(), $period, IpcrStatus::Returned);

        $totals = $this->stats()->totals(new DashboardScope());

        $this->assertSame(1, $totals['final']);
        $this->assertSame(2, $totals['returned']);
    }

    public function test_the_average_rating_uses_only_rated_ipcrs(): void
    {
        $period = IpcrPeriod::factory()->create();

        $this->ipcrFor(Employee::factory()->create(), $period, IpcrStatus::Approved, 4.0);
        $this->ipcrFor(Employee::factory()->create(), $period, IpcrStatus::Approved, 5.0);
        $this->ipcrFor(Employee::factory()->create(), $period, IpcrStatus::Draft);

        $this->assertSame(4.5, $this->stats()->totals(new DashboardScope())['avg_rating']);
    }

    public function test_the_average_is_null_when_nothing_is_rated(): void
    {
        $period = IpcrPeriod::factory()->create();
        $this->ipcrFor(Employee::factory()->create(), $period, IpcrStatus::Draft);

        $this->assertNull($this->stats()->totals(new DashboardScope())['avg_rating']);
    }

    // -----------------------------------------------------------------
    // Scoping
    // -----------------------------------------------------------------

    public function test_the_period_filter_narrows_the_counts(): void
    {
        $first = IpcrPeriod::factory()->create();
        $second = IpcrPeriod::factory()->create();
        $employee = Employee::factory()->create();

        // Same employee, different periods - allowed by the unique index.
        $this->ipcrFor($employee, $first, IpcrStatus::Approved);
        $this->ipcrFor($employee, $second, IpcrStatus::Draft);

        $scoped = $this->stats()->totals(new DashboardScope(periodId: $first->id));

        $this->assertSame(1, $scoped['total']);
        $this->assertSame(1, $scoped['approved']);
    }

    public function test_the_division_filter_narrows_the_counts(): void
    {
        $period = IpcrPeriod::factory()->create();
        $medical = Division::factory()->create();
        $finance = Division::factory()->create();

        $this->ipcrFor(Employee::factory()->create(['division_id' => $medical->id]), $period, IpcrStatus::Approved);
        $this->ipcrFor(Employee::factory()->create(['division_id' => $finance->id]), $period, IpcrStatus::Draft);

        $this->assertSame(1, $this->stats()->totals(new DashboardScope(divisionId: $medical->id))['total']);
    }

    public function test_the_section_filter_narrows_the_counts(): void
    {
        $period = IpcrPeriod::factory()->create();
        $nursing = Section::factory()->create();
        $pharmacy = Section::factory()->create();

        $this->ipcrFor(Employee::factory()->create(['section_id' => $nursing->id]), $period, IpcrStatus::Approved);
        $this->ipcrFor(Employee::factory()->create(['section_id' => $pharmacy->id]), $period, IpcrStatus::Draft);

        $this->assertSame(1, $this->stats()->totals(new DashboardScope(sectionId: $nursing->id))['total']);
    }

    // -----------------------------------------------------------------
    // Workflow track
    // -----------------------------------------------------------------

    /**
     * Routing differs by who the employee is: a rank and file IPCR is assessed
     * by their section head, a section head's by their division head. Knowing
     * the split tells HR which chain is carrying the load.
     */
    public function test_it_splits_ipcrs_by_workflow_track(): void
    {
        $period = IpcrPeriod::factory()->create();
        $division = Division::factory()->create();
        $section = Section::factory()->create(['division_id' => $division->id]);

        $head = Employee::factory()->create(['section_id' => $section->id]);
        $section->update(['section_head_employee_id' => $head->id]);

        $first = Employee::factory()->create(['section_id' => $section->id]);
        $second = Employee::factory()->create(['section_id' => $section->id]);

        $this->ipcrFor($head, $period, IpcrStatus::Submitted);
        $this->ipcrFor($first, $period, IpcrStatus::Submitted);
        $this->ipcrFor($second, $period, IpcrStatus::Approved);

        $totals = $this->stats()->totals(new DashboardScope());

        $this->assertSame(1, $totals['section_head_track']);
        $this->assertSame(2, $totals['employee_track']);
    }

    // -----------------------------------------------------------------
    // Breakdowns
    // -----------------------------------------------------------------

    public function test_it_breaks_the_figures_down_by_division(): void
    {
        $period = IpcrPeriod::factory()->create();
        $division = Division::factory()->create(['name' => 'Medical Services']);
        $this->ipcrFor(Employee::factory()->create(['division_id' => $division->id]), $period, IpcrStatus::Approved, 4.0);
        $this->ipcrFor(Employee::factory()->create(['division_id' => $division->id]), $period, IpcrStatus::Draft);

        $rows = $this->stats()->byDivision(new DashboardScope());
        $row = collect($rows)->firstWhere('id', $division->id);

        $this->assertNotNull($row);
        $this->assertSame('Medical Services', $row['name']);
        $this->assertSame(2, $row['total']);
        $this->assertSame(1, $row['approved']);
        $this->assertSame(1, $row['draft']);
        $this->assertSame(4.0, $row['avg_rating']);
    }

    public function test_it_reports_totals_and_approvals_per_period(): void
    {
        $period = IpcrPeriod::factory()->create(['name' => 'January - June 2026']);

        $this->ipcrFor(Employee::factory()->create(), $period, IpcrStatus::Approved);
        $this->ipcrFor(Employee::factory()->create(), $period, IpcrStatus::Draft);

        $rows = $this->stats()->byPeriod(new DashboardScope());
        $row = collect($rows)->firstWhere('label', 'January - June 2026');

        $this->assertSame(2, $row['count']);
        $this->assertSame(1, $row['approved']);
    }

    public function test_recent_activity_returns_the_newest_first(): void
    {
        $period = IpcrPeriod::factory()->create();
        $old = Employee::factory()->create(['first_name' => 'Older', 'last_name' => 'Record']);
        $new = Employee::factory()->create(['first_name' => 'Newer', 'last_name' => 'Record']);

        // Written straight to the table: an Eloquent update would stamp
        // updated_at with the current time and defeat the test.
        $older = $this->ipcrFor($old, $period, IpcrStatus::Draft);
        $newer = $this->ipcrFor($new, $period, IpcrStatus::Approved);

        \DB::table('ipcrs')->where('id', $older->id)->update(['updated_at' => now()->subDays(3)]);
        \DB::table('ipcrs')->where('id', $newer->id)->update(['updated_at' => now()]);

        $recent = $this->stats()->recentActivity(new DashboardScope());

        $this->assertSame('Newer Record', $recent->first()->employee->full_name);
    }

    // -----------------------------------------------------------------
    // Who has not submitted
    // -----------------------------------------------------------------

    public function test_it_lists_active_employees_with_no_submission(): void
    {
        $period = IpcrPeriod::factory()->create();

        $submitted = Employee::factory()->create(['first_name' => 'Has', 'last_name' => 'Submitted']);
        $missing = Employee::factory()->create(['first_name' => 'Not', 'last_name' => 'Yet']);

        $this->ipcrFor($submitted, $period, IpcrStatus::Submitted);

        $names = $this->stats()->notSubmitted(new DashboardScope(periodId: $period->id))
            ->map(fn (Employee $e): string => $e->full_name);

        $this->assertContains('Not Yet', $names);
        $this->assertNotContains('Has Submitted', $names);
    }

    /** A draft has not been sent to anyone, so its owner has not submitted. */
    public function test_a_draft_leaves_the_employee_on_the_not_submitted_list(): void
    {
        $period = IpcrPeriod::factory()->create();
        $employee = Employee::factory()->create(['first_name' => 'Still', 'last_name' => 'Drafting']);

        $this->ipcrFor($employee, $period, IpcrStatus::Draft);

        $names = $this->stats()->notSubmitted(new DashboardScope(periodId: $period->id))
            ->map(fn (Employee $e): string => $e->full_name);

        $this->assertContains('Still Drafting', $names);
    }

    public function test_inactive_employees_are_not_chased(): void
    {
        $period = IpcrPeriod::factory()->create();
        Employee::factory()->create(['is_active' => false, 'first_name' => 'Gone', 'last_name' => 'Away']);

        $names = $this->stats()->notSubmitted(new DashboardScope(periodId: $period->id))
            ->map(fn (Employee $e): string => $e->full_name);

        $this->assertNotContains('Gone Away', $names);
    }
}
