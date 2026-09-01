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
 * The dashboard belongs to whoever has people to look after.
 *
 * For somebody with nobody under them it was a page about themselves, and
 * their own IPCR says all of it faster. So a plain employee has no dashboard
 * at all - not a link, not a route - and lands on their own sheets instead.
 *
 * A head gets what they cannot see anywhere else: the roster of their section
 * or division for the open period, and which of them has not sent anything in.
 */
class HeadDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function userFor(Employee $employee): User
    {
        $user = User::factory()->create();
        $employee->update(['user_id' => $user->id]);

        return $user->fresh();
    }

    /** An employee in a section, with nobody under them. */
    private function staff(Section $section, string $last = 'Staff'): Employee
    {
        return Employee::factory()->create([
            'section_id'  => $section->id,
            'division_id' => $section->division_id,
            'last_name'   => $last,
        ]);
    }

    private function sectionIn(Division $division, string $name = 'Nursing'): Section
    {
        return Section::factory()->create(['name' => $name, 'division_id' => $division->id]);
    }

    private function headOf(Section $section): Employee
    {
        $head = $this->staff($section, 'Head');
        $section->update(['section_head_employee_id' => $head->id]);

        return $head->fresh();
    }

    private function sidebar(User $user): string
    {
        return $this->actingAs($user)->get(route('ipcrs.index'))->assertOk()->getContent();
    }

    // -----------------------------------------------------------------
    // Who has one
    // -----------------------------------------------------------------

    public function test_a_plain_employee_has_no_dashboard_link(): void
    {
        $employee = $this->staff($this->sectionIn(Division::factory()->create()));

        $this->assertStringNotContainsString(
            route('dashboard'),
            $this->sidebar($this->userFor($employee)),
        );
    }

    public function test_a_section_head_has_one(): void
    {
        $head = $this->headOf($this->sectionIn(Division::factory()->create()));

        $this->assertStringContainsString(route('dashboard'), $this->sidebar($this->userFor($head)));
    }

    public function test_a_division_head_has_one(): void
    {
        $division = Division::factory()->create();
        $head = $this->staff($this->sectionIn($division), 'Chief');
        $division->update(['division_head_employee_id' => $head->id]);

        $this->assertStringContainsString(route('dashboard'), $this->sidebar($this->userFor($head->fresh())));
    }

    /** They look after the hospital rather than a section, but they look after it. */
    public function test_an_administrator_has_one(): void
    {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);
        $user->assignRole('admin');

        $this->assertStringContainsString(route('dashboard'), $this->sidebar($user->fresh()));
    }

    // -----------------------------------------------------------------
    // And where the rest are sent
    // -----------------------------------------------------------------

    public function test_a_plain_employee_asking_for_the_dashboard_lands_on_their_ipcrs(): void
    {
        $employee = $this->staff($this->sectionIn(Division::factory()->create()));

        $this->actingAs($this->userFor($employee))
            ->get('/dashboard')
            ->assertRedirect(route('ipcrs.index'));
    }

    public function test_the_root_sends_a_plain_employee_to_their_ipcrs(): void
    {
        $employee = $this->staff($this->sectionIn(Division::factory()->create()));

        $this->actingAs($this->userFor($employee))->get('/')->assertRedirect(route('ipcrs.index'));
    }

    // -----------------------------------------------------------------
    // What a head sees
    // -----------------------------------------------------------------

    public function test_a_section_head_sees_the_people_in_their_section(): void
    {
        $section = $this->sectionIn(Division::factory()->create());
        $head = $this->headOf($section);
        $this->staff($section, 'Dalangin');

        $this->actingAs($this->userFor($head))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dalangin');
    }

    public function test_they_do_not_see_another_sections_people(): void
    {
        $division = Division::factory()->create();
        $head = $this->headOf($this->sectionIn($division, 'Nursing'));

        $this->staff($this->sectionIn($division, 'Budget'), 'Pajutan');

        $this->actingAs($this->userFor($head))
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Pajutan');
    }

    /** A division head sees every section under them, not just their own. */
    public function test_a_division_head_sees_the_whole_division(): void
    {
        $division = Division::factory()->create();
        $head = $this->staff($this->sectionIn($division, 'Office of the Chief'), 'Miro');
        $division->update(['division_head_employee_id' => $head->id]);

        $this->staff($this->sectionIn($division, 'Nursing'), 'Onde');
        $this->staff($this->sectionIn(Division::factory()->create(), 'Elsewhere'), 'Garcia');

        $html = $this->actingAs($this->userFor($head->fresh()))->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Onde', $html);
        $this->assertStringNotContainsString('Garcia', $html);
    }

    /** Their own sheet is the card at the top; it does not need a second row. */
    public function test_a_head_is_not_on_their_own_roster(): void
    {
        $section = $this->sectionIn(Division::factory()->create());
        $head = $this->headOf($section);

        // Somebody else, or there is no roster to be absent from.
        $this->staff($section, 'Somebody');

        $html = $this->actingAs($this->userFor($head))->get('/dashboard')->assertOk()->getContent();

        $start = strpos($html, 'data-team-roster');
        $this->assertNotFalse($start, 'No roster rendered.');

        $roster = substr($html, $start);

        $this->assertStringNotContainsString($head->full_name, $roster);
    }

    /**
     * Nobody above them.
     *
     * A division head may be filed in one of their own sections, and the Chief
     * has to sit somewhere too. Their sheets go upward - the Chief assesses a
     * division head - so they are not the section head's business, and a name
     * on the roster that cannot be chased is noise.
     */
    public function test_a_section_head_does_not_see_the_division_head(): void
    {
        $division = Division::factory()->create();
        $section = $this->sectionIn($division);
        $head = $this->headOf($section);

        // Filed in the very section this head runs.
        $above = $this->staff($section, 'Bigboss');
        $division->update(['division_head_employee_id' => $above->id]);

        $this->actingAs($this->userFor($head))
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Bigboss');
    }

    public function test_a_section_head_does_not_see_the_chief(): void
    {
        $section = $this->sectionIn(Division::factory()->create());
        $head = $this->headOf($section);

        $this->staff($section, 'Chiefly')->update(['is_chief_of_hospital' => true]);

        $this->actingAs($this->userFor($head))
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Chiefly');
    }

    /** Everyone in the division, section heads included. */
    public function test_a_division_head_sees_the_section_heads_under_them(): void
    {
        $division = Division::factory()->create();
        $head = $this->staff($this->sectionIn($division, 'Office'), 'Miro');
        $division->update(['division_head_employee_id' => $head->id]);

        $sectionHead = $this->headOf($this->sectionIn($division, 'Nursing'));
        $sectionHead->update(['last_name' => 'Sectionhead']);

        $this->actingAs($this->userFor($head->fresh()))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Sectionhead');
    }

    public function test_a_division_head_does_not_see_the_chief(): void
    {
        $division = Division::factory()->create();
        $head = $this->staff($this->sectionIn($division, 'Office'), 'Miro');
        $division->update(['division_head_employee_id' => $head->id]);

        $this->staff($this->sectionIn($division, 'Nursing'), 'Chiefly')
            ->update(['is_chief_of_hospital' => true]);

        $this->actingAs($this->userFor($head->fresh()))
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Chiefly');
    }

    // -----------------------------------------------------------------
    // Who has not sent anything in
    // -----------------------------------------------------------------

    public function test_the_roster_says_who_has_not_submitted(): void
    {
        $period = IpcrPeriod::factory()->create(['status' => 'open']);
        $section = $this->sectionIn(Division::factory()->create());
        $head = $this->headOf($section);

        $sent = $this->staff($section, 'Sender');
        Ipcr::factory()->create([
            'employee_id'    => $sent->id,
            'ipcr_period_id' => $period->id,
            'status'         => IpcrStatus::Submitted,
            'submitted_at'   => now(),
        ]);

        $this->staff($section, 'Silent');

        $html = $this->actingAs($this->userFor($head))->get('/dashboard')->assertOk()->getContent();
        $roster = substr($html, (int) strpos($html, 'data-team-roster'));

        $this->assertStringContainsString('Sender', $roster);
        $this->assertStringContainsString('Silent', $roster);
        $this->assertStringContainsString('Not started', $roster);
    }

    // -----------------------------------------------------------------
    // A head who runs a unit outside the one they are filed in
    // -----------------------------------------------------------------

    /**
     * Somebody may head a section in one division while their plantilla
     * position sits in another. The unit they run is where they work: their
     * IPCR goes to the head of THAT division, so that is the roster their name
     * belongs on. Filed under the division on their position, they were chased
     * by a head with no say over them and invisible to the head who assesses
     * them.
     */
    private function borrowedSectionHead(Section $leads, Section $filedIn): Employee
    {
        $head = $this->staff($filedIn, 'Guico');
        $leads->update(['section_head_employee_id' => $head->id]);

        return $head->fresh();
    }

    public function test_a_division_head_sees_a_section_head_who_runs_one_of_their_sections(): void
    {
        $administrative = Division::factory()->create();
        $residential = Division::factory()->create();

        $chief = $this->staff($this->sectionIn($administrative, 'Office'), 'Onde');
        $administrative->update(['division_head_employee_id' => $chief->id]);

        $this->borrowedSectionHead(
            leads: $this->sectionIn($administrative, 'Human Resource Development'),
            filedIn: $this->sectionIn($residential, 'Health Information Management'),
        );

        $this->actingAs($this->userFor($chief->fresh()))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Guico');
    }

    public function test_the_division_they_are_only_filed_in_does_not_see_them(): void
    {
        $administrative = Division::factory()->create();
        $residential = Division::factory()->create();

        $chief = $this->staff($this->sectionIn($residential, 'Office'), 'Pajutan');
        $residential->update(['division_head_employee_id' => $chief->id]);

        $this->borrowedSectionHead(
            leads: $this->sectionIn($administrative, 'Human Resource Development'),
            filedIn: $this->sectionIn($residential, 'Health Information Management'),
        );

        $this->actingAs($this->userFor($chief->fresh()))
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Guico');
    }

    public function test_the_section_head_they_are_filed_under_does_not_see_them(): void
    {
        $administrative = Division::factory()->create();
        $residential = Division::factory()->create();

        $filedIn = $this->sectionIn($residential, 'Health Information Management');
        $garcia = $this->headOf($filedIn);
        $garcia->update(['last_name' => 'Garcia']);

        $this->borrowedSectionHead(
            leads: $this->sectionIn($administrative, 'Human Resource Development'),
            filedIn: $filedIn,
        );

        $this->actingAs($this->userFor($garcia->fresh()))
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Guico');
    }
}
