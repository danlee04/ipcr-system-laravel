<?php

namespace Tests\Feature\Admin;

use App\Enums\IpcrMode;
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
 * HR and administrators need to look at any IPCR without being able to touch
 * it. The dashboard tells them who has not submitted; without this they cannot
 * click through to a single one of those people.
 *
 * Read-only is the whole point: viewing is opened up, editing, assessing,
 * approving and deleting are not.
 */
class IpcrOversightTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function someoneElsesIpcr(array $overrides = []): Ipcr
    {
        $owner = User::factory()->create();
        Employee::factory()->create(['user_id' => $owner->id, 'last_name' => 'Santos']);

        return Ipcr::factory()->create(array_merge([
            'employee_id' => $owner->fresh()->employee->id,
            'status'      => IpcrStatus::Draft,
            'mode'        => IpcrMode::TargetsOnly,
        ], $overrides));
    }

    // -----------------------------------------------------------------
    // Who may look
    // -----------------------------------------------------------------

    public function test_an_admin_can_view_any_ipcr(): void
    {
        $ipcr = $this->someoneElsesIpcr();

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('ipcrs.show', $ipcr))
            ->assertOk()
            ->assertSee('Santos');
    }

    public function test_an_hr_user_can_view_any_ipcr(): void
    {
        $ipcr = $this->someoneElsesIpcr();

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('ipcrs.show', $ipcr))
            ->assertOk();
    }

    public function test_an_unrelated_employee_still_cannot(): void
    {
        $ipcr = $this->someoneElsesIpcr();
        $this->seed(RoleSeeder::class);

        $bystander = User::factory()->create();
        Employee::factory()->create(['user_id' => $bystander->id]);

        $this->actingAs($bystander->fresh())
            ->get(route('ipcrs.show', $ipcr))
            ->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Looking is not touching
    // -----------------------------------------------------------------

    /**
     * The show page gated its owner controls on the IPCR's status alone. Once
     * an administrator can see the page, a draft would offer them the owner's
     * add, edit and submit controls - all of which fail on POST.
     */
    public function test_an_admin_does_not_get_the_owners_controls_on_a_draft(): void
    {
        $ipcr = $this->someoneElsesIpcr();

        $response = $this->actingAs($this->userWithRole('admin'))
            ->get(route('ipcrs.show', $ipcr))
            ->assertOk();

        $response->assertDontSee('Submit for Assessment');
        $response->assertDontSee(route('ipcrs.items.catalog', $ipcr), false);
    }

    public function test_the_owner_still_gets_them(): void
    {
        $ipcr = $this->someoneElsesIpcr();

        $this->actingAs($ipcr->employee->user)
            ->get(route('ipcrs.show', $ipcr))
            ->assertOk()
            ->assertSee('Submit for Assessment');
    }

    public function test_an_admin_cannot_edit_someone_elses_ipcr(): void
    {
        $ipcr = $this->someoneElsesIpcr();

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('ipcrs.items.catalog', $ipcr), [
                'job_function_ids' => [1],
            ])
            ->assertForbidden();
    }

    public function test_an_admin_cannot_approve_someone_elses_ipcr(): void
    {
        $ipcr = $this->someoneElsesIpcr(['status' => IpcrStatus::Assessed]);

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('ipcrs.approve', $ipcr))
            ->assertForbidden();
    }

    /**
     * Scrapping is the one thing an administrator may do to somebody else's
     * IPCR, and only while it is still moving. Setting the system up leaves
     * half-built records that reached a Section Head, past the owner's reach;
     * see DeletingAnIpcrTest for the whole rule, the approved case included.
     */
    public function test_an_admin_can_delete_someone_elses_draft(): void
    {
        $ipcr = $this->someoneElsesIpcr();

        $this->actingAs($this->userWithRole('admin'))
            ->delete(route('ipcrs.destroy', $ipcr))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('ipcrs', ['id' => $ipcr->id]);
    }

    /**
     * Regression: both sides of the approver comparison could be null.
     *
     * A user with no employee record has a null employee id, and an IPCR that
     * has never been submitted has no approver stamped on it. Comparing those
     * two directly matched, handing assessment and approval to anyone without
     * an employee record.
     */
    public function test_a_user_with_no_employee_record_cannot_assess_an_unrouted_ipcr(): void
    {
        $ipcr = $this->someoneElsesIpcr(['status' => IpcrStatus::Submitted]);
        $stranger = $this->userWithRole('admin');

        $this->assertNull($stranger->employee, 'This test is meaningless if the user has an employee.');
        $this->assertNull($ipcr->assessor_employee_id, 'This test needs an IPCR with no assessor.');

        $this->assertFalse($stranger->can('assess', $ipcr));

        $this->actingAs($stranger)->post(route('ipcrs.assess', $ipcr))->assertForbidden();
    }

    public function test_a_user_with_no_employee_record_cannot_finalize_an_unrouted_ipcr(): void
    {
        $ipcr = $this->someoneElsesIpcr(['status' => IpcrStatus::Assessed]);
        $stranger = $this->userWithRole('admin');

        $this->assertNull($ipcr->final_approver_employee_id);
        $this->assertFalse($stranger->can('finalize', $ipcr));

        $this->actingAs($stranger)->post(route('ipcrs.approve', $ipcr))->assertForbidden();
    }

    // -----------------------------------------------------------------
    // The list
    // -----------------------------------------------------------------

    public function test_the_admin_list_shows_every_ipcr(): void
    {
        $this->someoneElsesIpcr();
        $this->someoneElsesIpcr();

        $response = $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.ipcrs.index'))
            ->assertOk();

        $this->assertSame(2, $response->viewData('ipcrs')->total());
    }

    public function test_the_admin_list_is_paginated(): void
    {
        $period = IpcrPeriod::factory()->create();

        for ($i = 0; $i < 23; $i++) {
            Ipcr::factory()->create([
                'employee_id'    => Employee::factory()->create()->id,
                'ipcr_period_id' => $period->id,
            ]);
        }

        $response = $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.ipcrs.index'))
            ->assertOk();

        $this->assertCount(20, $response->viewData('ipcrs'));
    }

    public function test_the_list_can_be_filtered_by_status(): void
    {
        $this->someoneElsesIpcr(['status' => IpcrStatus::Draft]);
        $this->someoneElsesIpcr(['status' => IpcrStatus::Approved]);

        $response = $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.ipcrs.index', ['status' => 'approved']))
            ->assertOk();

        $this->assertSame(1, $response->viewData('ipcrs')->total());
        $this->assertSame(IpcrStatus::Approved, $response->viewData('ipcrs')->first()->status);
    }

    public function test_the_list_can_be_filtered_by_period(): void
    {
        $wanted = IpcrPeriod::factory()->create();
        $this->someoneElsesIpcr(['ipcr_period_id' => $wanted->id]);
        $this->someoneElsesIpcr();

        $response = $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.ipcrs.index', ['period' => $wanted->id]))
            ->assertOk();

        $this->assertSame(1, $response->viewData('ipcrs')->total());
    }

    public function test_the_list_can_be_filtered_by_division(): void
    {
        $division = Division::factory()->create();
        $inside = User::factory()->create();
        Employee::factory()->create(['user_id' => $inside->id, 'division_id' => $division->id]);
        Ipcr::factory()->create(['employee_id' => $inside->fresh()->employee->id]);

        $this->someoneElsesIpcr();

        $response = $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.ipcrs.index', ['division' => $division->id]))
            ->assertOk();

        $this->assertSame(1, $response->viewData('ipcrs')->total());
    }

    public function test_the_list_can_be_filtered_by_section(): void
    {
        $section = Section::factory()->create();
        $inside = User::factory()->create();
        Employee::factory()->create(['user_id' => $inside->id, 'section_id' => $section->id]);
        Ipcr::factory()->create(['employee_id' => $inside->fresh()->employee->id]);

        $this->someoneElsesIpcr();

        $response = $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.ipcrs.index', ['section' => $section->id]))
            ->assertOk();

        $this->assertSame(1, $response->viewData('ipcrs')->total());
    }

    public function test_the_list_can_be_searched_by_employee_name(): void
    {
        $this->someoneElsesIpcr();

        $other = User::factory()->create();
        Employee::factory()->create(['user_id' => $other->id, 'last_name' => 'Bautista']);
        Ipcr::factory()->create(['employee_id' => $other->fresh()->employee->id]);

        $response = $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.ipcrs.index', ['search' => 'bautista']))
            ->assertOk();

        $this->assertSame(1, $response->viewData('ipcrs')->total());
        $this->assertSame('Bautista', $response->viewData('ipcrs')->first()->employee->last_name);
    }

    public function test_a_plain_user_cannot_reach_the_admin_list(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.ipcrs.index'))
            ->assertForbidden();
    }
}
