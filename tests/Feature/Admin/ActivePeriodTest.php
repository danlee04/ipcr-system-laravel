<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\IpcrPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One rating period is active, and an administrator says which.
 *
 * The app has always used exactly one - every IPCR is created against it - but
 * nothing said which one it was. Any number of periods could sit open at once
 * and the code quietly took the latest by start date. Two periods opened on
 * the same day made that a coin toss, and nothing on screen admitted a choice
 * was being made at all.
 *
 * So opening a period now closes whichever one was open. There is one, it is
 * named, and it was chosen.
 */
class ActivePeriodTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function period(array $attributes = []): IpcrPeriod
    {
        static $year = 2030;

        return IpcrPeriod::factory()->create($attributes + [
            'year' => $year++,
            'name' => 'Period ' . $year,
        ]);
    }

    private function makeActive(IpcrPeriod $period): void
    {
        $this->actingAs($this->admin())
            ->patch(route('admin.periods.status', $period), ['open' => 1])
            ->assertRedirect(route('admin.periods.index'));
    }

    // -----------------------------------------------------------------
    // Exactly one
    // -----------------------------------------------------------------

    public function test_making_one_active_closes_the_one_that_was(): void
    {
        $outgoing = $this->period(['status' => 'open']);
        $incoming = $this->period(['status' => 'closed']);

        $this->makeActive($incoming);

        $this->assertSame('open', $incoming->fresh()->status);
        $this->assertSame('closed', $outgoing->fresh()->status, 'Only one period may be active.');
    }

    public function test_a_newly_created_period_becomes_the_active_one(): void
    {
        $outgoing = $this->period(['status' => 'open']);

        $this->actingAs($this->admin())->post(route('admin.periods.store'), [
            'name'       => 'January - June 2031',
            'year'       => 2031,
            'type'       => 'first_semester',
            'start_date' => '2031-01-01',
            'end_date'   => '2031-06-30',
        ])->assertSessionHasNoErrors();

        $this->assertSame('closed', $outgoing->fresh()->status);
        $this->assertSame('January - June 2031', IpcrPeriod::active()->name);
    }

    public function test_closing_the_active_period_leaves_none(): void
    {
        $period = $this->period(['status' => 'open']);

        $this->actingAs($this->admin())
            ->patch(route('admin.periods.status', $period), ['open' => 0]);

        $this->assertNull(IpcrPeriod::active());
    }

    /** Making the active one active again is not a reason to close it. */
    public function test_reactivating_the_active_period_is_harmless(): void
    {
        $period = $this->period(['status' => 'open']);

        $this->makeActive($period);

        $this->assertSame('open', $period->fresh()->status);
        $this->assertSame($period->id, IpcrPeriod::active()->id);
    }

    // -----------------------------------------------------------------
    // Reading it back
    // -----------------------------------------------------------------

    public function test_active_returns_the_open_period(): void
    {
        $this->period(['status' => 'closed']);
        $open = $this->period(['status' => 'open']);

        $this->assertSame($open->id, IpcrPeriod::active()?->id);
    }

    public function test_active_is_null_when_nothing_is_open(): void
    {
        $this->period(['status' => 'closed']);

        $this->assertNull(IpcrPeriod::active());
    }

    /**
     * Two open periods cannot be reached through the screens any more, but a
     * seeder or a hand-edited row could still leave them. The answer must not
     * depend on row order.
     */
    public function test_the_answer_is_stable_even_if_two_were_left_open(): void
    {
        $older = $this->period(['status' => 'open', 'start_date' => '2030-01-01']);
        $newer = $this->period(['status' => 'open', 'start_date' => '2030-07-01']);

        $this->assertSame($newer->id, IpcrPeriod::active()?->id);
        $this->assertSame($newer->id, IpcrPeriod::active()?->id, 'Asked twice, answered the same.');
    }

    // -----------------------------------------------------------------
    // What the screen says
    // -----------------------------------------------------------------

    public function test_the_list_names_the_active_period(): void
    {
        $this->period(['status' => 'closed', 'name' => 'Last semester']);
        $this->period(['status' => 'open', 'name' => 'This semester']);

        $this->actingAs($this->admin())
            ->get(route('admin.periods.index'))
            ->assertOk()
            ->assertSee('This semester')
            ->assertSee('Active');
    }

    public function test_the_action_says_what_it_does(): void
    {
        $this->period(['status' => 'closed']);

        $this->actingAs($this->admin())
            ->get(route('admin.periods.index'))
            ->assertOk()
            ->assertSee('Make active');
    }

    public function test_the_list_warns_when_no_period_is_active(): void
    {
        $this->period(['status' => 'closed']);

        $this->actingAs($this->admin())
            ->get(route('admin.periods.index'))
            ->assertOk()
            ->assertSee('No rating period is active');
    }

    // -----------------------------------------------------------------
    // What the rest of the app does with it
    // -----------------------------------------------------------------

    public function test_a_new_ipcr_is_created_against_the_active_period(): void
    {
        $this->period(['status' => 'closed']);
        $active = $this->period(['status' => 'open']);

        $employee = Employee::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($employee->user)
            ->post(route('ipcrs.store'), ['mode' => 'targets_only'])
            ->assertSessionMissing('error');

        $this->assertSame($active->id, $employee->ipcrs()->latest('id')->first()->ipcr_period_id);
    }

    public function test_nobody_can_start_an_ipcr_while_none_is_active(): void
    {
        $this->period(['status' => 'closed']);

        $employee = Employee::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($employee->user)
            ->get(route('ipcrs.create'))
            ->assertSessionHas('error');

        $this->assertSame(0, $employee->ipcrs()->count());
    }
}
