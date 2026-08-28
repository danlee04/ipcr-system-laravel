<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What an employee can see about themselves.
 *
 * The profile page used to hold two account fields and nothing else, while
 * everything that decides how the IPCR behaves - the section they sit in, the
 * post they hold, who assesses their sheet - lived only in the admin screens
 * they cannot open. So the first time anyone learned who their assessor was,
 * it was because the IPCR had already gone there.
 *
 * None of it is editable here. HR keeps the record; this page only says what
 * the record currently holds, and who to ask when it is wrong.
 */
class ProfileDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function chart(): array
    {
        $division = Division::factory()->create(['name' => 'Medical Services']);
        $section = Section::factory()->create([
            'name' => 'Nursing Service', 'division_id' => $division->id,
        ]);

        $sectionHead = Employee::factory()->create([
            'first_name' => 'Marcilina', 'last_name' => 'Garcia', 'section_id' => $section->id,
        ]);
        $divisionHead = Employee::factory()->create([
            'first_name' => 'Ruth', 'last_name' => 'Pajutan', 'division_id' => $division->id,
        ]);

        $section->update(['section_head_employee_id' => $sectionHead->id]);
        $division->update(['division_head_employee_id' => $divisionHead->id]);

        return compact('division', 'section', 'sectionHead', 'divisionHead');
    }

    private function employee(array $chart, array $attributes = []): User
    {
        $user = User::factory()->create();

        Employee::factory()->create(array_merge([
            'user_id'         => $user->id,
            'employee_number' => 'DTRC-0042',
            'first_name'      => 'Jenelyn',
            'last_name'       => 'Dalangin',
            'section_id'      => $chart['section']->id,
            'position_id'     => Position::factory()->create(['title' => 'Nurse II'])->id,
        ], $attributes));

        return $user->fresh();
    }

    private function profile(User $user): string
    {
        return $this->actingAs($user)->get(route('profile.edit'))->assertOk()->getContent();
    }

    // -----------------------------------------------------------------
    // Their own record
    // -----------------------------------------------------------------

    public function test_the_page_shows_the_record_hr_holds(): void
    {
        $html = $this->profile($this->employee($this->chart()));

        $this->assertStringContainsString('DTRC-0042', $html);
        $this->assertStringContainsString('Jenelyn Dalangin', $html);
        $this->assertStringContainsString('Nurse II', $html);
        $this->assertStringContainsString('Medical Services', $html);
        $this->assertStringContainsString('Nursing Service', $html);
    }

    public function test_a_designation_they_hold_is_listed(): void
    {
        $chart = $this->chart();
        $user = $this->employee($chart);

        $user->employee->designations()->attach(
            Designation::factory()->create(['title' => 'OIC - Budget'])->id,
            ['is_active' => true, 'start_date' => now()->subMonth()],
        );

        $this->assertStringContainsString('OIC - Budget', $this->profile($user->fresh()));
    }

    /** The post is what puts other people's IPCRs in their inbox. */
    public function test_the_post_they_hold_is_named(): void
    {
        $chart = $this->chart();
        $user = User::factory()->create();

        $employee = Employee::factory()->create([
            'user_id' => $user->id, 'section_id' => $chart['section']->id,
        ]);
        $chart['section']->update(['section_head_employee_id' => $employee->id]);

        $this->assertStringContainsString('Section Head', $this->profile($user->fresh()));
    }

    // -----------------------------------------------------------------
    // Where their IPCR goes
    // -----------------------------------------------------------------

    public function test_it_names_who_assesses_and_who_gives_the_final_approval(): void
    {
        $html = $this->profile($this->employee($this->chart()));

        $this->assertStringContainsString('For Assessment', $html);
        $this->assertStringContainsString('Marcilina Garcia', $html);

        $this->assertStringContainsString('For Final Approval', $html);
        $this->assertStringContainsString('Ruth Pajutan', $html);
    }

    /**
     * A chain that cannot be resolved says what is missing.
     *
     * The routing exception already names the gap and who fixes it, and an
     * employee who cannot submit deserves to read that here rather than
     * discover it at the moment they press Submit.
     */
    public function test_a_broken_chain_says_what_is_missing(): void
    {
        $chart = $this->chart();
        $chart['section']->update(['section_head_employee_id' => null]);

        $html = $this->profile($this->employee($chart));

        $this->assertStringContainsString('No Section Head is assigned', $html);
        $this->assertStringNotContainsString('Marcilina Garcia', $html);
    }

    /** An account with no employee record still gets a working page. */
    public function test_an_account_with_no_employee_record_still_loads(): void
    {
        $html = $this->profile(User::factory()->create());

        $this->assertStringContainsString('Update Password', $html);
        $this->assertStringNotContainsString('For Assessment', $html);
    }

    // -----------------------------------------------------------------
    // The password, at the foot, with an eye on it
    // -----------------------------------------------------------------

    public function test_the_password_section_sits_below_the_account_details(): void
    {
        $html = $this->profile($this->employee($this->chart()));

        $this->assertLessThan(
            strpos($html, 'Update Password'),
            strpos($html, 'DTRC-0042'),
            'The record should come before the password form.',
        );
    }

    /** One toggle per password field: current, new, and the confirmation. */
    public function test_every_password_field_can_be_shown(): void
    {
        $html = $this->profile($this->employee($this->chart()));

        $this->assertSame(3, substr_count($html, "show ? 'text' : 'password'"));
        $this->assertSame(3, substr_count($html, "show ? 'Hide password' : 'Show password'"));
    }
}
