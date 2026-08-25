<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The security boundary for the whole admin area.
 *
 * Every admin route is listed in adminRoutes(). A new admin route added
 * without protection fails here, which is the point: protection lives on the
 * route group, and this test is what proves the group still covers everything.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function adminRoutes(): array
    {
        return [
            'divisions' => ['admin.divisions.index'],
            'job titles' => ['admin.job-titles.index'],
            'employees' => ['admin.employees.index'],
        ];
    }

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    #[DataProvider('adminRoutes')]
    public function test_a_guest_is_sent_to_login(string $routeName): void
    {
        $this->seed(RoleSeeder::class);

        $this->get(route($routeName))->assertRedirect(route('login'));
    }

    #[DataProvider('adminRoutes')]
    public function test_a_signed_in_non_admin_is_forbidden(string $routeName): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route($routeName))->assertForbidden();
    }

    #[DataProvider('adminRoutes')]
    public function test_an_admin_gets_through(string $routeName): void
    {
        $this->actingAs($this->admin())->get(route($routeName))->assertOk();
    }

    /**
     * HR does the same setup work as an administrator, so the group admits
     * both roles. Asserted per route so a future route cannot quietly lock
     * HR out of a screen they are expected to run.
     */
    #[DataProvider('adminRoutes')]
    public function test_an_hr_user_gets_through(string $routeName): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('hr');

        $this->actingAs($user)->get(route($routeName))->assertOk();
    }

    /**
     * The seeded admin has no Employee record, so every admin page must render
     * for a user whose `employee` relation is null. Asserted directly rather
     * than left to chance: IpcrController already aborts 403 when an employee
     * is missing, and it would be easy to copy that habit into an admin screen
     * without noticing.
     */
    #[DataProvider('adminRoutes')]
    public function test_an_admin_without_an_employee_record_can_still_load_the_page(string $routeName): void
    {
        $admin = $this->admin();

        $this->assertNull($admin->employee, 'This test is meaningless if the admin has an employee.');

        $this->actingAs($admin)->get(route($routeName))->assertOk();
    }
}
