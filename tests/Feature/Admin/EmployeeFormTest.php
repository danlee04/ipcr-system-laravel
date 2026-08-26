<?php

namespace Tests\Feature\Admin;

use App\Enums\EmploymentStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * The shape of the create/edit employee form.
 *
 * Three things it has to get right, because HR fills this in for every person
 * in the hospital and a wrong record here misroutes their IPCR:
 *
 *   - the login is its own thing, not a column beside the employee number
 *   - the placement narrows: Division, then Section, then Position, each one
 *     limiting the next, so an impossible combination cannot be described
 *   - employment status offers only what DTRC actually hires under
 */
class EmployeeFormTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name'        => 'Juan',
            'last_name'         => 'Dela Cruz',
            'employee_number'   => 'DTRC-2001',
            'employment_status' => EmploymentStatus::Permanent->value,
        ], $overrides);
    }

    /** A division holding one section holding one position. */
    private function placement(): array
    {
        $division = Division::factory()->create(['name' => 'Administrative Division']);
        $section = Section::factory()->create(['division_id' => $division->id, 'name' => 'HRD Section']);
        $position = Position::factory()->create(['section_id' => $section->id, 'title' => 'HR Officer II']);

        return [$division, $section, $position];
    }

    private function formHtml(): string
    {
        $this->placement();

        return $this->actingAs($this->admin())
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->getContent();
    }

    // -----------------------------------------------------------------
    // Employment status
    // -----------------------------------------------------------------

    public function test_only_the_three_statuses_dtrc_hires_under_are_offered(): void
    {
        $this->assertSame(
            ['permanent', 'job_order', 'contract_of_service'],
            array_column(EmploymentStatus::cases(), 'value')
        );
    }

    public function test_each_status_reads_the_way_it_is_written_on_paper(): void
    {
        $this->assertSame('Permanent', EmploymentStatus::Permanent->label());
        $this->assertSame('Job Order', EmploymentStatus::JobOrder->label());
        $this->assertSame('Contract of Service', EmploymentStatus::ContractOfService->label());
    }

    public function test_a_status_that_is_no_longer_offered_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload(['employment_status' => 'casual']))
            ->assertSessionHasErrors('employment_status');
    }

    public function test_the_form_shows_the_written_labels(): void
    {
        $html = $this->formHtml();

        $this->assertStringContainsString('Contract of Service', $html);
        $this->assertStringNotContainsString('Coterminous', $html);
    }

    // -----------------------------------------------------------------
    // Date hired is gone
    // -----------------------------------------------------------------

    public function test_the_form_no_longer_asks_for_a_date_hired(): void
    {
        $this->assertStringNotContainsString('name="date_hired"', $this->formHtml());
    }

    // -----------------------------------------------------------------
    // The login, on its own
    // -----------------------------------------------------------------

    public function test_the_login_fields_sit_in_their_own_card(): void
    {
        $html = $this->formHtml();

        $this->assertStringContainsString('Login account', $html);
        $this->assertStringContainsString('name="password"', $html);
    }

    public function test_an_administrator_can_set_the_password(): void
    {
        $this->actingAs($this->admin())->post(route('admin.employees.store'), $this->payload([
            'email'    => 'juan@dtrc.test',
            'password' => 'ospital2026',
        ]))->assertSessionHasNoErrors();

        $this->assertTrue(Auth::attempt(['email' => 'juan@dtrc.test', 'password' => 'ospital2026']));
    }

    /** Leaving it blank still works: one is generated and shown once. */
    public function test_a_blank_password_is_generated_instead(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload(['email' => 'juan@dtrc.test', 'password' => '']))
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'juan@dtrc.test')->first();

        $this->assertNotNull($user);
        $this->assertStringContainsString('Temporary password', session('status'));
    }

    public function test_a_password_too_short_to_be_worth_setting_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload([
                'email' => 'juan@dtrc.test', 'password' => 'abc',
            ]))
            ->assertSessionHasErrors('password');
    }

    /** A password with no email has nothing to be the password of. */
    public function test_a_password_without_an_email_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload(['password' => 'ospital2026']))
            ->assertSessionHasErrors('password');
    }

    // -----------------------------------------------------------------
    // Division, then Section, then Position
    // -----------------------------------------------------------------

    public function test_the_three_placement_fields_appear_in_that_order(): void
    {
        $html = $this->formHtml();

        $division = strpos($html, 'name="division_id"');
        $section = strpos($html, 'name="section_id"');
        $position = strpos($html, 'name="position_id"');

        $this->assertNotFalse($division);
        $this->assertLessThan($section, $division, 'Division must come before Section.');
        $this->assertLessThan($position, $section, 'Section must come before Position.');
    }

    /**
     * Each select carries the key the one before it narrows on. Without these
     * the three can describe a placement that does not exist - a position from
     * one section under a division it does not belong to.
     */
    public function test_each_step_narrows_the_next(): void
    {
        [$division, $section, $position] = $this->placement();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-division="' . $division->id . '"', $html);
        $this->assertStringContainsString('data-section="' . $section->id . '"', $html);
    }

    /**
     * The section is already chosen in the select before this one, so
     * repeating it on every position line only makes the list harder to read.
     */
    public function test_the_position_options_name_the_position_and_nothing_else(): void
    {
        $html = $this->formHtml();

        preg_match('/<select name="position_id".*?<\/select>/s', $html, $matches);

        $this->assertNotEmpty($matches, 'The position picker should be on the page.');
        $this->assertStringContainsString('HR Officer II', $matches[0]);
        $this->assertStringNotContainsString('HRD Section', $matches[0]);
        $this->assertStringNotContainsString('Office-wide', $matches[0]);
    }

    /** A four-column form needs more room than Breeze's 2xl modal gives it. */
    public function test_the_form_opens_in_a_wide_modal(): void
    {
        $this->assertStringContainsString('sm:max-w-4xl', $this->formHtml());
    }

    public function test_a_section_from_another_division_is_refused(): void
    {
        [$division] = $this->placement();
        $strayDivision = Division::factory()->create();
        $straySection = Section::factory()->create(['division_id' => $strayDivision->id]);

        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload([
                'division_id' => $division->id,
                'section_id'  => $straySection->id,
            ]))
            ->assertSessionHasErrors('section_id');
    }

    public function test_a_position_from_another_section_is_refused(): void
    {
        [$division, $section] = $this->placement();
        $strayPosition = Position::factory()->create([
            'section_id' => Section::factory()->create(['division_id' => $division->id])->id,
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload([
                'division_id' => $division->id,
                'section_id'  => $section->id,
                'position_id' => $strayPosition->id,
            ]))
            ->assertSessionHasErrors('position_id');
    }

    public function test_a_matching_placement_is_accepted(): void
    {
        [$division, $section, $position] = $this->placement();

        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload([
                'division_id' => $division->id,
                'section_id'  => $section->id,
                'position_id' => $position->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('employees', [
            'employee_number' => 'DTRC-2001',
            'division_id'     => $division->id,
            'section_id'      => $section->id,
            'position_id'     => $position->id,
        ]);
    }

    /**
     * A Division Head holds no section, and the Chief of Hospital holds
     * neither. Nothing above may make those impossible to record.
     */
    public function test_a_placement_may_stop_at_the_division_or_be_left_empty(): void
    {
        [$division] = $this->placement();

        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload(['division_id' => $division->id]))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload(['employee_number' => 'DTRC-2002']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Employee::count());
    }
}
