<?php

namespace Tests\Feature\Admin;

use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use App\Services\IpcrRoutingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function admin(): User
    {
        return $this->userWithRole('admin');
    }

    /** A minimally valid create payload. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name'        => 'Juan',
            'last_name'         => 'Dela Cruz',
            'employee_number'   => 'DTRC-1001',
            'email'             => 'juan@dtrc.test',
            'employment_status' => 'permanent',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // Creating
    // -----------------------------------------------------------------

    public function test_an_admin_can_create_an_employee_with_a_login_account(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload())
            ->assertRedirect(route('admin.employees.index'));

        $this->assertDatabaseHas('employees', [
            'employee_number' => 'DTRC-1001', 'first_name' => 'Juan', 'is_active' => true,
        ]);
        $this->assertDatabaseHas('users', ['email' => 'juan@dtrc.test']);

        $employee = Employee::where('employee_number', 'DTRC-1001')->first();
        $this->assertNotNull($employee->user, 'The employee must be linked to the account that was created.');
        $this->assertSame('Juan Dela Cruz', $employee->user->name);
    }

    public function test_the_new_account_gets_the_employee_role(): void
    {
        $this->actingAs($this->admin())->post(route('admin.employees.store'), $this->payload());

        $this->assertTrue(User::where('email', 'juan@dtrc.test')->first()->hasRole('employee'));
    }

    public function test_an_admin_can_grant_the_hr_role_instead(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload(['role' => 'hr']));

        $this->assertTrue(User::where('email', 'juan@dtrc.test')->first()->hasRole('hr'));
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload(['role' => 'superuser']))
            ->assertSessionHasErrors('role');
    }

    /**
     * The generated password is shown once and must actually work - a message
     * that names a password the account does not have is worse than none.
     */
    public function test_the_temporary_password_is_shown_once_and_works(): void
    {
        $this->actingAs($this->admin())->post(route('admin.employees.store'), $this->payload());

        $status = (string) session('status');
        $this->assertStringContainsString('Temporary password:', $status);

        preg_match('/Temporary password: (\S+)/', $status, $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'No password was shown in the flash message.');

        $this->assertTrue(
            Auth::attempt(['email' => 'juan@dtrc.test', 'password' => $matches[1]]),
            'The password shown to the administrator does not sign the employee in.'
        );
    }

    public function test_an_employee_can_be_created_inside_a_section_and_position(): void
    {
        $division = Division::factory()->create();
        $section = Section::factory()->create(['division_id' => $division->id]);
        $position = Position::factory()->create();

        $this->actingAs($this->admin())->post(route('admin.employees.store'), $this->payload([
            'division_id' => $division->id,
            'section_id'  => $section->id,
            'position_id' => $position->id,
        ]));

        $employee = Employee::where('employee_number', 'DTRC-1001')->first();
        $this->assertSame($section->id, $employee->section_id);
        $this->assertSame($division->id, $employee->division_id);
        $this->assertSame($position->id, $employee->position_id);
    }

    // -----------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------

    public function test_an_employee_needs_a_first_and_last_name(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload(['first_name' => '', 'last_name' => '']))
            ->assertSessionHasErrors(['first_name', 'last_name']);
    }

    public function test_an_employee_number_must_be_unique(): void
    {
        Employee::factory()->create(['employee_number' => 'DTRC-1001']);

        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload())
            ->assertSessionHasErrors('employee_number');
    }

    public function test_the_email_must_not_already_belong_to_an_account(): void
    {
        User::factory()->create(['email' => 'juan@dtrc.test']);

        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload())
            ->assertSessionHasErrors('email');
    }

    public function test_an_unknown_employment_status_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload(['employment_status' => 'volunteer']))
            ->assertSessionHasErrors('employment_status');
    }

    // -----------------------------------------------------------------
    // Updating
    // -----------------------------------------------------------------

    public function test_an_admin_can_update_an_employee_and_the_linked_account(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@dtrc.test']);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'last_name' => 'Old']);

        $this->actingAs($this->admin())->put(route('admin.employees.update', $employee), [
            'first_name'        => 'Maria',
            'last_name'         => 'Santos',
            'employee_number'   => $employee->employee_number,
            'email'             => 'maria@dtrc.test',
            'employment_status' => 'permanent',
        ])->assertRedirect(route('admin.employees.index'));

        $this->assertSame('Santos', $employee->fresh()->last_name);
        $this->assertSame('maria@dtrc.test', $user->fresh()->email);
        $this->assertSame('Maria Santos', $user->fresh()->name);
    }

    public function test_updating_keeps_the_employees_own_employee_number(): void
    {
        $employee = Employee::factory()->create(['employee_number' => 'DTRC-2002']);

        $this->actingAs($this->admin())->put(route('admin.employees.update', $employee), [
            'first_name'        => $employee->first_name,
            'last_name'         => $employee->last_name,
            'employee_number'   => 'DTRC-2002',
            'employment_status' => 'permanent',
        ])->assertSessionHasNoErrors();
    }

    public function test_an_admin_can_deactivate_and_reactivate_an_employee(): void
    {
        $employee = Employee::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())->patch(route('admin.employees.active', $employee), ['active' => false]);
        $this->assertFalse($employee->fresh()->is_active);

        $this->actingAs($this->admin())->patch(route('admin.employees.active', $employee), ['active' => true]);
        $this->assertTrue($employee->fresh()->is_active);
    }

    public function test_deleting_an_employee_only_soft_deletes_them(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->admin())->delete(route('admin.employees.destroy', $employee));

        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
    }

    // -----------------------------------------------------------------
    // Chief of Hospital - there can be only one
    // -----------------------------------------------------------------

    public function test_marking_a_new_chief_of_hospital_clears_the_previous_one(): void
    {
        $outgoing = Employee::factory()->chiefOfHospital()->create();
        $incoming = Employee::factory()->create();

        $this->actingAs($this->admin())->put(route('admin.employees.update', $incoming), [
            'first_name'           => $incoming->first_name,
            'last_name'            => $incoming->last_name,
            'employee_number'      => $incoming->employee_number,
            'employment_status'    => 'permanent',
            'is_chief_of_hospital' => 1,
        ]);

        $this->assertTrue($incoming->fresh()->is_chief_of_hospital);
        $this->assertFalse(
            $outgoing->fresh()->is_chief_of_hospital,
            'Two employees must never be Chief of Hospital at the same time.'
        );
    }

    /**
     * The payoff for this screen.
     *
     * A Division Head's IPCR is assessed and approved by the Chief of Hospital.
     * Nothing in the system could set that flag before, so IpcrRoutingService
     * threw for every Division Head and Section Head no matter how carefully
     * the divisions and sections had been set up.
     */
    public function test_setting_a_chief_of_hospital_unblocks_a_division_heads_routing(): void
    {
        $division = Division::factory()->create();
        $divisionHead = Employee::factory()->create(['division_id' => $division->id]);
        $division->update(['division_head_employee_id' => $divisionHead->id]);

        $chief = Employee::factory()->create();
        $routing = app(IpcrRoutingService::class);

        try {
            $routing->resolve($divisionHead->fresh());
            $this->fail('Routing should fail while no Chief of Hospital is set.');
        } catch (\App\Exceptions\IpcrRoutingException $e) {
            $this->assertStringContainsString('Chief of Hospital', $e->getMessage());
        }

        $this->actingAs($this->admin())->put(route('admin.employees.update', $chief), [
            'first_name'           => $chief->first_name,
            'last_name'            => $chief->last_name,
            'employee_number'      => $chief->employee_number,
            'employment_status'    => 'permanent',
            'is_chief_of_hospital' => 1,
        ]);

        $chain = $routing->resolve($divisionHead->fresh());

        $this->assertSame($chief->id, $chain->assessor->id);
        $this->assertSame($chief->id, $chain->finalApprover->id);
    }

    // -----------------------------------------------------------------
    // Listing and access
    // -----------------------------------------------------------------

    public function test_the_page_lists_employees(): void
    {
        Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);

        $this->actingAs($this->admin())
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee('Maria Santos');
    }

    public function test_an_hr_user_can_manage_employees(): void
    {
        $this->actingAs($this->userWithRole('hr'))
            ->get(route('admin.employees.index'))
            ->assertOk();

        $this->actingAs($this->userWithRole('hr'))
            ->post(route('admin.employees.store'), $this->payload(['email' => 'second@dtrc.test', 'employee_number' => 'DTRC-1002']))
            ->assertRedirect(route('admin.employees.index'));
    }

    public function test_a_plain_user_cannot_reach_the_employees_page(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.employees.index'))
            ->assertForbidden();
    }

    public function test_a_plain_user_cannot_create_an_employee(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.employees.store'), $this->payload())
            ->assertForbidden();
    }
}
