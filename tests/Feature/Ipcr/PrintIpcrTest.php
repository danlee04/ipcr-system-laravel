<?php

namespace Tests\Feature\Ipcr;

use App\Enums\FunctionCategory;
use App\Enums\IpcrStatus;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\IpcrPeriod;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The printable IPCR.
 *
 * This is what the hospital actually files: the signed sheet, not the row in
 * the database. It has to carry everything an approver signs off on and must
 * never invent a number that has not been given.
 */
class PrintIpcrTest extends TestCase
{
    use RefreshDatabase;

    private function employeeUser(array $attributes = []): User
    {
        $user = User::factory()->create();
        Employee::factory()->create(array_merge(['user_id' => $user->id], $attributes));

        return $user->fresh();
    }

    private function ipcrWithItems(User $owner, array $overrides = []): Ipcr
    {
        $ipcr = Ipcr::factory()->create(array_merge([
            'employee_id'    => $owner->employee->id,
            'ipcr_period_id' => IpcrPeriod::factory()->create(['name' => 'January - June 2026'])->id,
            'position_title' => 'Nurse II',
            'office_name'    => 'Nursing Section',
            'status'         => IpcrStatus::Approved,
        ], $overrides));

        IpcrItem::factory()->create([
            'ipcr_id' => $ipcr->id, 'category' => FunctionCategory::Core, 'weight' => 100,
            'output' => 'Provides direct patient care', 'success_indicator' => 'Seen within 30 minutes',
            'quality_rating' => 5, 'efficiency_rating' => 4, 'timeliness_rating' => 3, 'average_rating' => 4,
        ]);

        IpcrItem::factory()->create([
            'ipcr_id' => $ipcr->id, 'category' => FunctionCategory::Support, 'weight' => 100,
            'output' => 'Reviews the monthly report', 'success_indicator' => 'Submitted by the 5th',
            'quality_rating' => 3, 'efficiency_rating' => 3, 'timeliness_rating' => 3, 'average_rating' => 3,
        ]);

        return $ipcr->fresh();
    }

    // -----------------------------------------------------------------
    // Who may print
    // -----------------------------------------------------------------

    public function test_the_owner_can_print_their_own_ipcr(): void
    {
        $owner = $this->employeeUser();
        $ipcr = $this->ipcrWithItems($owner);

        $this->actingAs($owner)->get(route('ipcrs.print', $ipcr))->assertOk();
    }

    public function test_the_assessor_can_print_it(): void
    {
        $owner = $this->employeeUser();
        $assessor = $this->employeeUser();
        $ipcr = $this->ipcrWithItems($owner, ['assessor_employee_id' => $assessor->employee->id]);

        $this->actingAs($assessor)->get(route('ipcrs.print', $ipcr))->assertOk();
    }

    public function test_hr_can_print_anyones_ipcr(): void
    {
        $this->seed(RoleSeeder::class);
        $hr = User::factory()->create();
        $hr->assignRole('hr');

        $ipcr = $this->ipcrWithItems($this->employeeUser());

        $this->actingAs($hr)->get(route('ipcrs.print', $ipcr))->assertOk();
    }

    public function test_an_unrelated_employee_cannot_print_it(): void
    {
        $ipcr = $this->ipcrWithItems($this->employeeUser());

        $this->actingAs($this->employeeUser())
            ->get(route('ipcrs.print', $ipcr))
            ->assertForbidden();
    }

    // -----------------------------------------------------------------
    // What the sheet carries
    // -----------------------------------------------------------------

    public function test_it_shows_who_the_ipcr_belongs_to_and_for_which_period(): void
    {
        $owner = $this->employeeUser(['first_name' => 'Maria', 'last_name' => 'Santos']);
        $ipcr = $this->ipcrWithItems($owner);

        $this->actingAs($owner)->get(route('ipcrs.print', $ipcr))
            ->assertOk()
            ->assertSee('Maria Santos')
            ->assertSee('January - June 2026')
            ->assertSee('Nurse II')
            ->assertSee('Nursing Section');
    }

    public function test_it_lists_the_functions_with_their_indicators_and_marks(): void
    {
        $owner = $this->employeeUser();
        $ipcr = $this->ipcrWithItems($owner);

        $this->actingAs($owner)->get(route('ipcrs.print', $ipcr))
            ->assertOk()
            ->assertSee('Provides direct patient care')
            ->assertSee('Seen within 30 minutes')
            ->assertSee('Reviews the monthly report');
    }

    public function test_the_functions_are_grouped_under_their_category_headings(): void
    {
        $owner = $this->employeeUser();
        $ipcr = $this->ipcrWithItems($owner);

        $this->actingAs($owner)->get(route('ipcrs.print', $ipcr))
            ->assertOk()
            ->assertSeeInOrder(['Core Function', 'Provides direct patient care', 'Support Function', 'Reviews the monthly report']);
    }

    public function test_it_shows_the_final_rating_when_there_is_one(): void
    {
        $owner = $this->employeeUser();
        $ipcr = $this->ipcrWithItems($owner, [
            'final_numerical_rating'  => 4.600,
            'final_adjectival_rating' => 'Outstanding',
        ]);

        $this->actingAs($owner)->get(route('ipcrs.print', $ipcr))
            ->assertOk()
            ->assertSee('4.600')
            ->assertSee('Outstanding');
    }

    /** A blank sheet is honest; a fabricated rating on a signed form is not. */
    public function test_an_unrated_ipcr_prints_blanks_rather_than_numbers(): void
    {
        $owner = $this->employeeUser();

        $ipcr = Ipcr::factory()->create([
            'employee_id'    => $owner->employee->id,
            'ipcr_period_id' => IpcrPeriod::factory()->create()->id,
            'status'         => IpcrStatus::Draft,
        ]);

        IpcrItem::factory()->create([
            'ipcr_id' => $ipcr->id, 'category' => FunctionCategory::Core, 'weight' => 100,
            'output' => 'A target with no marks yet',
        ]);

        $response = $this->actingAs($owner)->get(route('ipcrs.print', $ipcr->fresh()))->assertOk();

        $response->assertSee('A target with no marks yet');
        $response->assertDontSee('Outstanding');
        $response->assertDontSee('Very Satisfactory');
    }

    public function test_the_signature_block_names_the_actual_approvers(): void
    {
        $owner = $this->employeeUser(['first_name' => 'Maria', 'last_name' => 'Santos']);
        $assessor = $this->employeeUser(['first_name' => 'Ramon', 'last_name' => 'Bautista']);
        $approver = $this->employeeUser(['first_name' => 'Elena', 'last_name' => 'Cruz']);

        $ipcr = $this->ipcrWithItems($owner, [
            'assessor_employee_id'       => $assessor->employee->id,
            'final_approver_employee_id' => $approver->employee->id,
        ]);

        $this->actingAs($owner)->get(route('ipcrs.print', $ipcr))
            ->assertOk()
            ->assertSee('Ramon Bautista')
            ->assertSee('Elena Cruz');
    }

    public function test_the_position_falls_back_to_the_employees_current_one(): void
    {
        $position = Position::factory()->create(['title' => 'Medical Officer IV']);
        $owner = $this->employeeUser(['position_id' => $position->id]);

        $ipcr = $this->ipcrWithItems($owner, ['position_title' => null]);

        $this->actingAs($owner)->get(route('ipcrs.print', $ipcr))
            ->assertOk()
            ->assertSee('Medical Officer IV');
    }

    // -----------------------------------------------------------------
    // It is a sheet, not an app screen
    // -----------------------------------------------------------------

    public function test_the_print_view_carries_none_of_the_app_chrome(): void
    {
        $owner = $this->employeeUser();
        $ipcr = $this->ipcrWithItems($owner);

        $response = $this->actingAs($owner)->get(route('ipcrs.print', $ipcr))->assertOk();

        $response->assertDontSee('id="app-sidebar"', false);
        $response->assertDontSee('Log out');
    }

    public function test_the_ipcr_page_offers_a_print_link(): void
    {
        $owner = $this->employeeUser();
        $ipcr = $this->ipcrWithItems($owner);

        $this->actingAs($owner)->get(route('ipcrs.show', $ipcr))
            ->assertOk()
            ->assertSee(route('ipcrs.print', $ipcr), false);
    }

    // -----------------------------------------------------------------
    // The letterhead
    // -----------------------------------------------------------------

    /**
     * The agency, not the software.
     *
     * The sheet used to print app.name under "Republic of the Philippines",
     * which named the system on a document that is filed as the hospital's.
     */
    public function test_the_letterhead_names_the_agency(): void
    {
        config([
            'agency.name'    => 'Dangerous Drugs Treatment and Rehabilitation Centre',
            'app.name'       => 'IPCR System',
        ]);

        $owner = $this->employeeUser();

        $this->actingAs($owner)->get(route('ipcrs.print', $this->ipcrWithItems($owner)))
            ->assertOk()
            ->assertSee('Dangerous Drugs Treatment and Rehabilitation Centre')
            ->assertDontSee('IPCR System');
    }

    public function test_the_address_is_left_out_when_there_is_none(): void
    {
        config(['agency.name' => 'A Hospital', 'agency.address' => null]);

        $owner = $this->employeeUser();
        $html = $this->actingAs($owner)
            ->get(route('ipcrs.print', $this->ipcrWithItems($owner)))
            ->assertOk()
            ->getContent();

        // Three lines of letterhead means an empty one is showing.
        $this->assertSame(2, substr_count($html, 'class="agency'));
    }
}
