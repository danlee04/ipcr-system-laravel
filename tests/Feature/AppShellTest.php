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
        // Given an Employee record, because "My IPCRs" is only shown to people
        // who have one - IpcrController aborts 403 without it.
        $user = User::factory()->create();
        \App\Models\Employee::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user->fresh())->get('/dashboard');

        $response->assertOk();
        $response->assertSee('id="app-sidebar"', false);
        $response->assertSee('IPCR System');
        $response->assertSee('Dashboard');
        $response->assertSee('My IPCRs');
        $response->assertSee('Log out');
    }

    /**
     * The collapse control sits in the brand bar, not at the foot.
     *
     * It belongs beside the thing it resizes, and at the foot it was the last
     * item in a column of links - a control dressed as a destination. Icon
     * only: the word "Collapse" was the widest label in the sidebar, and it
     * disappears in the one state where the button matters most.
     */
    public function test_the_collapse_control_sits_at_the_top_and_carries_no_label(): void
    {
        $user = User::factory()->create();
        \App\Models\Employee::factory()->create(['user_id' => $user->id]);

        $html = $this->actingAs($user->fresh())->get('/dashboard')->assertOk()->getContent();

        $toggle = strpos($html, 'toggleCollapsed()');
        $nav = strpos($html, '<nav');

        $this->assertNotFalse($toggle, 'No collapse control at all.');
        $this->assertLessThan($nav, $toggle, 'The collapse control is below the nav.');

        // One control, and it says what it does to a screen reader alone.
        $this->assertSame(1, substr_count($html, 'toggleCollapsed()'));
        $this->assertStringNotContainsString('>Collapse<', $html);
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
        $response->assertSee(config('agency.name'));
        $response->assertDontSee('id="app-sidebar"', false);
    }
}
