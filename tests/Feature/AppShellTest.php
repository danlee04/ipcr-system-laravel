<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the app shell. These assertions are small, but they catch the most
 * common breakages: a deleted partial, a renamed route, or the sidebar
 * crashing when no Employee is linked to the user.
 */
class AppShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sidebar_renders_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('id="app-sidebar"', false);
        $response->assertSee('IPCR System');
        $response->assertSee('Dashboard');
        $response->assertSee('My IPCRs');
        $response->assertSee('Log out');
    }

    /**
     * Not every user has an Employee record - the relation is null on new
     * accounts. The sidebar must not fall over; it shows the email instead of
     * an employee number.
     */
    public function test_the_sidebar_survives_a_user_with_no_employee_record(): void
    {
        $user = User::factory()->create(['email' => 'no.employee@example.test']);

        $this->assertNull($user->employee);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('no.employee@example.test');
    }

    public function test_the_login_page_uses_the_guest_shell(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('IPCR System');
        $response->assertSee('Individual Performance Commitment and Review');
        $response->assertDontSee('id="app-sidebar"', false);
    }
}
