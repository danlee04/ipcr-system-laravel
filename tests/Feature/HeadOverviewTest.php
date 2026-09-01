<?php

namespace Tests\Feature;

use App\Enums\IpcrStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrPeriod;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a section or division head is given for the unit they run.
 *
 * A list of names and badges answered "who has not sent anything in" and
 * nothing else. A head also has to know how far the whole unit has got, which
 * sheets are sitting with them, and which of them they can act on now - so the
 * page is a strip of figures, the records that have been sent in, and the
 * people still to chase.
 */
class HeadOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    // -----------------------------------------------------------------
    // Scaffolding
    // -----------------------------------------------------------------

    private function userFor(Employee $employee): User
    {
        $user = User::factory()->create();
        $employee->update(['user_id' => $user->id]);

        return $user->fresh();
    }

    private function staff(Section $section, string $last): Employee
    {
        return Employee::factory()->create([
            'section_id'  => $section->id,
            'division_id' => $section->division_id,
            'last_name'   => $last,
        ]);
    }

    private function sectionIn(Division $division, string $name): Section
    {
        return Section::factory()->create(['name' => $name, 'division_id' => $division->id]);
    }

    private function headOf(Section $section, string $last = 'Head'): Employee
    {
        $head = $this->staff($section, $last);
        $section->update(['section_head_employee_id' => $head->id]);

        return $head->fresh();
    }

    /** The page as this head sees it. */
    private function dashboard(Employee $head): string
    {
        return $this->actingAs($this->userFor($head))
            ->get('/dashboard')
            ->assertOk()
            ->getContent();
    }

    /**
     * One block of the page.
     *
     * Every block carries a data-head-* marker, so a piece of the page can be
     * asked about on its own - a name in the records table is not the same
     * answer as the same name in the list still to be chased.
     */
    private function block(string $html, string $marker): string
    {
        $start = strpos($html, $marker);
        $this->assertNotFalse($start, "The page has no {$marker} block.");

        $after = $start + strlen($marker);

        // The block ends where the next one begins, whichever kind that is -
        // otherwise one card's block swallows the three cards after it and any
        // figure in the strip would answer for all four.
        $ends = array_filter([strpos($html, 'data-head-', $after), strpos($html, 'data-kpi=', $after)], is_int(...));

        return $ends === [] ? substr($html, $start) : substr($html, $start, min($ends) - $start);
    }

    /** The number one card in the strip is showing. */
    private function figure(string $html, string $kpi): string
    {
        $block = $this->block($html, 'data-kpi="' . $kpi . '"');

        $this->assertSame(
            1,
            preg_match('/data-kpi-value[^>]*>\s*([0-9.]+)/', $block, $match),
            "The {$kpi} card shows no figure.",
        );

        return $match[1];
    }

    // -----------------------------------------------------------------
    // The masthead
    // -----------------------------------------------------------------

    public function test_the_masthead_names_the_unit_rather_than_the_head(): void
    {
        $head = $this->headOf($this->sectionIn(Division::factory()->create(), 'Nursing Section'));

        $html = $this->dashboard($head);

        $this->assertStringContainsString('Nursing Section', $html);
        $this->assertStringContainsString('Section IPCR overview', $html);
    }

    public function test_a_division_head_masthead_says_division(): void
    {
        $division = Division::factory()->create(['name' => 'Administrative Division']);
        $head = $this->staff($this->sectionIn($division, 'Office'), 'Onde');
        $division->update(['division_head_employee_id' => $head->id]);

        $html = $this->dashboard($head->fresh());

        $this->assertStringContainsString('Administrative Division', $html);
        $this->assertStringContainsString('Division IPCR overview', $html);
    }

    // -----------------------------------------------------------------
    // The strip of figures
    // -----------------------------------------------------------------

    public function test_the_strip_counts_the_unit_and_where_its_sheets_have_got_to(): void
    {
        $period = IpcrPeriod::factory()->create(['status' => 'open']);
        $section = $this->sectionIn(Division::factory()->create(), 'Nursing Section');
        $head = $this->headOf($section);

        $sheet = function (string $last, IpcrStatus $status) use ($section, $period): void {
            Ipcr::factory()->create([
                'employee_id'    => $this->staff($section, $last)->id,
                'ipcr_period_id' => $period->id,
                'status'         => $status,
                'submitted_at'   => now(),
            ]);
        };

        $sheet('Dalangin', IpcrStatus::Submitted);
        $sheet('Paloma', IpcrStatus::Assessed);
        $sheet('Pajutan', IpcrStatus::Approved);
        $this->staff($section, 'Silent');

        $html = $this->dashboard($head);

        // Four people under this head, and one sheet at each stage.
        $this->assertSame('4', $this->figure($html, 'people'));
        $this->assertSame('1', $this->figure($html, 'assessment'));
        $this->assertSame('1', $this->figure($html, 'final'));
        $this->assertSame('1', $this->figure($html, 'approved'));
    }

    // -----------------------------------------------------------------
    // The two tables, which never hold the same person
    // -----------------------------------------------------------------

    public function test_a_sheet_that_has_been_sent_in_is_in_the_records_table(): void
    {
        $period = IpcrPeriod::factory()->create(['status' => 'open']);
        $section = $this->sectionIn(Division::factory()->create(), 'Nursing Section');
        $head = $this->headOf($section);

        Ipcr::factory()->create([
            'employee_id'    => $this->staff($section, 'Dalangin')->id,
            'ipcr_period_id' => $period->id,
            'status'         => IpcrStatus::Submitted,
            'submitted_at'   => now(),
        ]);

        $html = $this->dashboard($head);

        $this->assertStringContainsString('Dalangin', $this->block($html, 'data-head-records'));
        $this->assertStringNotContainsString('Dalangin', $this->block($html, 'data-head-pending'));
    }

    public function test_somebody_who_has_started_nothing_is_on_the_list_to_chase(): void
    {
        IpcrPeriod::factory()->create(['status' => 'open']);
        $section = $this->sectionIn(Division::factory()->create(), 'Nursing Section');
        $head = $this->headOf($section);

        $this->staff($section, 'Silent');

        $html = $this->dashboard($head);

        $pending = $this->block($html, 'data-head-pending');

        $this->assertStringContainsString('Silent', $pending);
        $this->assertStringContainsString('Not started', $pending);
        $this->assertStringNotContainsString('Silent', $this->block($html, 'data-head-records'));
    }

    /**
     * A draft has never left the employee's hands, so its owner is still
     * somebody to chase - not a record the head can do anything with.
     */
    public function test_a_draft_is_on_the_list_to_chase_rather_than_in_the_records(): void
    {
        $period = IpcrPeriod::factory()->create(['status' => 'open']);
        $section = $this->sectionIn(Division::factory()->create(), 'Nursing Section');
        $head = $this->headOf($section);

        Ipcr::factory()->create([
            'employee_id'    => $this->staff($section, 'Drafter')->id,
            'ipcr_period_id' => $period->id,
            'status'         => IpcrStatus::Draft,
        ]);

        $html = $this->dashboard($head);

        $this->assertStringContainsString('Drafter', $this->block($html, 'data-head-pending'));
        $this->assertStringNotContainsString('Drafter', $this->block($html, 'data-head-records'));
    }

    public function test_the_list_to_chase_congratulates_an_empty_one(): void
    {
        $period = IpcrPeriod::factory()->create(['status' => 'open']);
        $section = $this->sectionIn(Division::factory()->create(), 'Nursing Section');
        $head = $this->headOf($section);

        Ipcr::factory()->create([
            'employee_id'    => $this->staff($section, 'Dalangin')->id,
            'ipcr_period_id' => $period->id,
            'status'         => IpcrStatus::Submitted,
            'submitted_at'   => now(),
        ]);

        $this->assertStringContainsString(
            'All caught up',
            $this->block($this->dashboard($head), 'data-head-pending'),
        );
    }

    /**
     * A designation is what a head is chasing them about.
     *
     * The position is the plantilla item; the designation is the job they are
     * actually doing this period, and the one their IPCR will be full of. A
     * head reading a list of names needs both to know who they are looking at.
     */
    public function test_the_list_to_chase_names_the_position_and_the_designation(): void
    {
        IpcrPeriod::factory()->create(['status' => 'open']);
        $section = $this->sectionIn(Division::factory()->create(), 'Nursing Section');
        $head = $this->headOf($section);

        $silent = $this->staff($section, 'Silent');
        $silent->update(['position_id' => \App\Models\Position::factory()->create(['title' => 'Statistician II'])->id]);
        $silent->designations()->attach(
            \App\Models\Designation::factory()->create(['title' => 'OIC - HRMO'])->id,
            ['is_active' => true],
        );

        $pending = $this->block($this->dashboard($head), 'data-head-pending');

        $this->assertStringContainsString('Position / Designation', $pending);
        $this->assertStringContainsString('Statistician II / OIC - HRMO', $pending);
    }

    /** Most people hold no designation, and an empty half of a pair is noise. */
    public function test_somebody_with_no_designation_is_named_by_position_alone(): void
    {
        IpcrPeriod::factory()->create(['status' => 'open']);
        $section = $this->sectionIn(Division::factory()->create(), 'Nursing Section');
        $head = $this->headOf($section);

        $silent = $this->staff($section, 'Silent');
        $silent->update(['position_id' => \App\Models\Position::factory()->create(['title' => 'Nurse III'])->id]);

        $pending = $this->block($this->dashboard($head), 'data-head-pending');

        $this->assertStringContainsString('Nurse III', $pending);
        $this->assertStringNotContainsString('Nurse III /', $pending);
    }

    // -----------------------------------------------------------------
    // What the head can do from here
    // -----------------------------------------------------------------

    public function test_a_sheet_waiting_on_this_head_is_offered_for_review(): void
    {
        $period = IpcrPeriod::factory()->create(['status' => 'open']);
        $section = $this->sectionIn(Division::factory()->create(), 'Nursing Section');
        $head = $this->headOf($section);

        $ipcr = Ipcr::factory()->create([
            'employee_id'          => $this->staff($section, 'Dalangin')->id,
            'ipcr_period_id'       => $period->id,
            'status'               => IpcrStatus::Submitted,
            'submitted_at'         => now(),
            'assessor_employee_id' => $head->id,
        ]);

        $records = $this->block($this->dashboard($head), 'data-head-records');

        $this->assertStringContainsString(route('ipcrs.show', $ipcr), $records);
        $this->assertStringContainsString('Review', $records);
    }

    /** Sitting with somebody else: they can look, and that is all. */
    public function test_a_sheet_routed_elsewhere_is_only_offered_for_viewing(): void
    {
        $period = IpcrPeriod::factory()->create(['status' => 'open']);
        $section = $this->sectionIn(Division::factory()->create(), 'Nursing Section');
        $head = $this->headOf($section);
        $somebodyElse = $this->staff($section, 'Elsewhere');

        Ipcr::factory()->create([
            'employee_id'          => $this->staff($section, 'Dalangin')->id,
            'ipcr_period_id'       => $period->id,
            'status'               => IpcrStatus::Submitted,
            'submitted_at'         => now(),
            'assessor_employee_id' => $somebodyElse->id,
        ]);

        $records = $this->block($this->dashboard($head), 'data-head-records');

        $this->assertStringContainsString('View', $records);
        $this->assertStringNotContainsString('Review', $records);
    }

    /** The rating is the point of the whole exercise, so it has a column. */
    public function test_the_records_table_shows_a_final_rating(): void
    {
        $period = IpcrPeriod::factory()->create(['status' => 'open']);
        $section = $this->sectionIn(Division::factory()->create(), 'Nursing Section');
        $head = $this->headOf($section);

        Ipcr::factory()->create([
            'employee_id'            => $this->staff($section, 'Dalangin')->id,
            'ipcr_period_id'         => $period->id,
            'status'                 => IpcrStatus::Approved,
            'submitted_at'           => now(),
            'final_numerical_rating' => 4.25,
        ]);

        $this->assertStringContainsString(
            '4.25',
            $this->block($this->dashboard($head), 'data-head-records'),
        );
    }
}
