<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Binabantayan ang app shell. Maliit lang ang mga assertion na ito, pero
 * nahuhuli nila ang pinakakaraniwang sira: nabura ang isang partial,
 * pinalitan ang pangalan ng route, o nag-crash ang sidebar kapag walang
 * naka-link na Employee ang user.
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
     * Hindi lahat ng user ay may Employee record - null ang relasyon sa
     * mga bagong account. Dapat hindi bumagsak ang sidebar; email ang
     * ipinapakita nito imbes na employee number.
     */
    public function test_the_sidebar_survives_a_user_with_no_employee_record(): void
    {
        $user = User::factory()->create(['email' => 'walang.employee@example.test']);

        $this->assertNull($user->employee);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('walang.employee@example.test');
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
