<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_three_roles_are_seeded(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertSame(
            ['admin', 'employee', 'hr'],
            Role::query()->orderBy('name')->pluck('name')->all()
        );
    }

    public function test_seeding_roles_twice_does_not_duplicate_them(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertSame(3, Role::query()->count());
    }

    public function test_the_demo_seeder_creates_an_admin_with_no_employee_record(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(DemoSeeder::class);

        $admin = User::where('email', 'admin@example.com')->first();

        $this->assertNotNull($admin, 'DemoSeeder did not create admin@example.com.');
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertNull($admin->employee, 'The admin must not have an Employee record.');
    }

    public function test_the_ipcr_flow_accounts_do_not_get_admin(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(DemoSeeder::class);

        foreach (['test@example.com', 'sectionhead@example.com', 'divisionhead@example.com'] as $email) {
            $this->assertFalse(
                User::where('email', $email)->first()->hasRole('admin'),
                $email . ' must not be an admin.'
            );
        }
    }
}
