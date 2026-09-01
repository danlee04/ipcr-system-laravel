<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The period slip: which period is open, and how long is left.
 *
 * It is the one thing everybody in the hospital wants twice a year, so it is
 * on the login page before anybody has signed in, and on the dashboard after
 * they have. Same component, two grounds.
 *
 * A submission deadline is a posted administrative date - the kind of thing
 * that goes on a bulletin board - so showing it to an anonymous visitor is
 * deliberate. Counts and names are a different matter and must never reach
 * that page.
 */
class PeriodSlipTest extends TestCase
{
    use RefreshDatabase;

    private function openPeriod(): IpcrPeriod
    {
        return IpcrPeriod::factory()->create([
            'name'                => 'July - December 2026',
            'status'              => 'open',
            'start_date'          => now()->subDays(60),
            'end_date'            => now()->addDays(120),
            'submission_deadline' => now()->addDays(12),
        ]);
    }

    // -----------------------------------------------------------------
    // On the way in
    // -----------------------------------------------------------------

    public function test_the_login_page_names_the_open_period_and_its_deadline(): void
    {
        $this->openPeriod();

        $this->get('/login')
            ->assertOk()
            ->assertSee('July - December 2026')
            ->assertSee('12 days left');
    }

    public function test_it_says_plainly_when_no_period_is_open(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('No rating period is open');
    }

    /**
     * A date, and nothing else.
     *
     * The deadline is posted; who has submitted against it is not. Nothing
     * about a named person or a count may reach a page anybody can load.
     */
    public function test_the_login_page_leaks_no_names_and_no_counts(): void
    {
        $period = $this->openPeriod();

        Ipcr::factory()->create([
            'employee_id'    => Employee::factory()->create([
                'first_name' => 'Mary Jane', 'last_name' => 'Guico',
            ])->id,
            'ipcr_period_id' => $period->id,
            'submitted_at'   => now(),
        ]);

        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('Mary Jane', $html);
        $this->assertStringNotContainsString('Guico', $html);
        $this->assertStringNotContainsString('submitted', $html);
    }

    /** Typing a password you cannot see is how people lock themselves out. */
    public function test_the_password_field_can_be_unmasked(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Show password');
    }

    // -----------------------------------------------------------------
    // And once inside
    // -----------------------------------------------------------------

    public function test_the_dashboard_carries_the_same_slip(): void
    {
        $this->openPeriod();

        // A head, because they are who has a dashboard.
        $user = User::factory()->create();
        $person = Employee::factory()->create(['user_id' => $user->id]);
        \App\Models\Section::factory()->create(['section_head_employee_id' => $person->id]);

        $html = $this->actingAs($user->fresh())->get('/dashboard')->assertOk()->getContent();

        // Named on the slip itself, not somewhere else on the page: the My IPCR
        // card also prints the period, and asserting on the bare name passed
        // even with the slip emptied out.
        $slip = strpos($html, 'data-period-slip');

        $this->assertNotFalse($slip, 'No slip on the dashboard.');

        $onTheSlip = substr($html, $slip, 600);

        $this->assertStringContainsString('July - December 2026', $onTheSlip);
        $this->assertStringContainsString('12 days left', $onTheSlip);
    }

    /**
     * Said once on the page.
     *
     * The administrator's rail used to carry a "This period" panel of its own.
     * With the slip in the masthead that is the same thing twice, and the same
     * thing twice is the thing nobody reads.
     */
    public function test_the_admin_rail_does_not_repeat_it(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->openPeriod();

        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);
        $user->assignRole('admin');

        $html = $this->actingAs($user->fresh())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('This period', $html);
        $this->assertSame(1, substr_count($html, 'data-period-slip'));
    }
}
