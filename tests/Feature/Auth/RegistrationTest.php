<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Public registration is closed on purpose.
 *
 * Accounts are created by HR or an administrator from the Employees screen, so
 * that every login is tied to an employee record with a division, section and
 * position already set. A self-registered account would have none of that and
 * could not take part in an IPCR at all.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_registration_screen_is_gone(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_nobody_can_register_themselves(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertSame(0, User::where('email', 'test@example.com')->count());
    }

    /**
     * Accounts are made by HR, alongside the employee record.
     *
     * The login page says so in words rather than offering a link. Several
     * Breeze partials still guard a sign-up link with Route::has('register'),
     * so if the route name ever comes back those links reappear silently.
     */
    public function test_the_register_route_name_is_not_registered(): void
    {
        $this->assertFalse(Route::has('register'));
    }
}
