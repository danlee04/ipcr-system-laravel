<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_sees_the_administration_group(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Administration');
        $response->assertSee('Divisions');
        $response->assertSee('Job Titles');
        $response->assertSee('Employees');
        $response->assertSee('Rating Periods');
        $response->assertSee('Job Functions');
    }

    public function test_an_hr_user_sees_the_administration_group(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('hr');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Administration');
        $response->assertSee('Employees');
    }

    public function test_a_non_admin_sees_no_administration_group(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('Administration');
        $response->assertDontSee('Job Titles');
    }
    /**
     * "My IPCRs" needs an Employee record: IpcrController aborts 403 without
     * one. The seeded administrator has none, so the link would be an
     * invitation to an error page.
     */
    public function test_a_user_with_no_employee_record_does_not_see_my_ipcrs(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->assertNull($user->employee, 'This test is meaningless if the admin has an employee.');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('My IPCRs');
        $response->assertSee('Administration');
    }

    public function test_an_employee_still_sees_my_ipcrs(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user->fresh())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('My IPCRs');
    }

    /** An administrator who is also an employee keeps both. */
    public function test_an_admin_who_is_an_employee_sees_my_ipcrs_and_administration(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');
        Employee::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user->fresh())->get('/dashboard');

        $response->assertOk();
        $response->assertSee('My IPCRs');
        $response->assertSee('Administration');
    }
}