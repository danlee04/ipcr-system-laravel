<?php

namespace Tests\Feature\Admin;

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
}
