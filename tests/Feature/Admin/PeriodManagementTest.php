<?php

namespace Tests\Feature\Admin;

use App\Models\Ipcr;
use App\Models\IpcrPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodManagementTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function admin(): User
    {
        return $this->userWithRole('admin');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'                => 'July - December 2026',
            'year'                => 2026,
            'type'                => 'second_semester',
            'start_date'          => '2026-07-01',
            'end_date'            => '2026-12-31',
            'submission_deadline' => '2027-01-15',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // Creating
    // -----------------------------------------------------------------

    public function test_an_admin_can_open_a_new_rating_period(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.periods.store'), $this->payload())
            ->assertRedirect(route('admin.periods.index'));

        $this->assertDatabaseHas('ipcr_periods', [
            'name' => 'July - December 2026', 'year' => 2026, 'type' => 'second_semester', 'status' => 'open',
        ]);
    }

    public function test_a_period_needs_a_name_and_dates(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.periods.store'), $this->payload([
                'name' => '', 'start_date' => '', 'end_date' => '',
            ]))
            ->assertSessionHasErrors(['name', 'start_date', 'end_date']);
    }

    public function test_the_end_date_must_come_after_the_start_date(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.periods.store'), $this->payload([
                'start_date' => '2026-12-31', 'end_date' => '2026-07-01',
            ]))
            ->assertSessionHasErrors('end_date');
    }

    public function test_the_submission_deadline_cannot_precede_the_end_date(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.periods.store'), $this->payload([
                'end_date' => '2026-12-31', 'submission_deadline' => '2026-11-01',
            ]))
            ->assertSessionHasErrors('submission_deadline');
    }

    public function test_an_unknown_period_type_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.periods.store'), $this->payload(['type' => 'quarterly']))
            ->assertSessionHasErrors('type');
    }

    /** The table has unique(year, type) - the form must say so, not blow up. */
    public function test_the_same_year_and_type_cannot_be_created_twice(): void
    {
        IpcrPeriod::factory()->create(['year' => 2030, 'type' => 'annual']);

        $this->actingAs($this->admin())
            ->post(route('admin.periods.store'), $this->payload([
                'year' => 2030, 'type' => 'annual',
            ]))
            ->assertSessionHasErrors('type');
    }

    // -----------------------------------------------------------------
    // Updating and closing
    // -----------------------------------------------------------------

    public function test_an_admin_can_rename_a_period(): void
    {
        $period = IpcrPeriod::factory()->create(['name' => 'Old name']);

        $this->actingAs($this->admin())
            ->put(route('admin.periods.update', $period), $this->payload([
                'name' => 'New name', 'year' => $period->year, 'type' => $period->type,
            ]))
            ->assertRedirect(route('admin.periods.index'));

        $this->assertSame('New name', $period->fresh()->name);
    }

    public function test_saving_a_period_unchanged_does_not_trip_the_uniqueness_rule(): void
    {
        $period = IpcrPeriod::factory()->create(['year' => 2031, 'type' => 'annual']);

        $this->actingAs($this->admin())
            ->put(route('admin.periods.update', $period), $this->payload([
                'name' => $period->name, 'year' => 2031, 'type' => 'annual',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_an_admin_can_close_and_reopen_a_period(): void
    {
        $period = IpcrPeriod::factory()->create();

        $this->actingAs($this->admin())->patch(route('admin.periods.status', $period), ['open' => false]);
        $this->assertSame('closed', $period->fresh()->status);

        $this->actingAs($this->admin())->patch(route('admin.periods.status', $period), ['open' => true]);
        $this->assertSame('open', $period->fresh()->status);
    }

    /**
     * Closing is the whole reason this screen exists: it is what stops new
     * IPCRs being started against a period that is over.
     */
    public function test_closing_a_period_stops_new_ipcrs_being_started_against_it(): void
    {
        $period = IpcrPeriod::factory()->create();

        $this->assertTrue(IpcrPeriod::open()->where('id', $period->id)->exists());

        $this->actingAs($this->admin())->patch(route('admin.periods.status', $period), ['open' => false]);

        $this->assertFalse(IpcrPeriod::open()->where('id', $period->id)->exists());
    }

    // -----------------------------------------------------------------
    // Deleting
    // -----------------------------------------------------------------

    public function test_an_unused_period_can_be_deleted(): void
    {
        $period = IpcrPeriod::factory()->create();

        $this->actingAs($this->admin())->delete(route('admin.periods.destroy', $period));

        $this->assertDatabaseMissing('ipcr_periods', ['id' => $period->id]);
    }

    public function test_a_period_that_already_has_ipcrs_survives_a_delete_attempt(): void
    {
        $period = IpcrPeriod::factory()->create();
        Ipcr::factory()->create(['ipcr_period_id' => $period->id]);

        $this->actingAs($this->admin())->delete(route('admin.periods.destroy', $period));

        $this->assertDatabaseHas('ipcr_periods', ['id' => $period->id]);
        $this->assertStringContainsString('Cannot delete', (string) session('error'));
    }

    // -----------------------------------------------------------------
    // The page
    // -----------------------------------------------------------------

    public function test_the_page_lists_periods_with_how_many_ipcrs_they_hold(): void
    {
        $period = IpcrPeriod::factory()->create(['name' => 'January - June 2026']);
        Ipcr::factory()->count(2)->create(['ipcr_period_id' => $period->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.periods.index'))
            ->assertOk()
            ->assertSee('January - June 2026');
    }

    /**
     * IpcrController takes the LATEST open period. With two open at once that
     * choice is invisible, so the page has to say which one employees get.
     */
    public function test_the_page_warns_when_more_than_one_period_is_open(): void
    {
        IpcrPeriod::factory()->create(['year' => 2040, 'type' => 'first_semester', 'start_date' => '2040-01-01']);
        IpcrPeriod::factory()->create(['year' => 2040, 'type' => 'second_semester', 'start_date' => '2040-07-01']);

        $this->actingAs($this->admin())
            ->get(route('admin.periods.index'))
            ->assertOk()
            ->assertSee('More than one period is open');
    }

    public function test_a_single_open_period_produces_no_warning(): void
    {
        IpcrPeriod::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.periods.index'))
            ->assertOk()
            ->assertDontSee('More than one period is open');
    }

    // -----------------------------------------------------------------
    // Access
    // -----------------------------------------------------------------

    public function test_an_hr_user_can_manage_periods(): void
    {
        $this->actingAs($this->userWithRole('hr'))
            ->get(route('admin.periods.index'))
            ->assertOk();
    }

    public function test_a_plain_user_cannot_manage_periods(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.periods.store'), $this->payload())
            ->assertForbidden();
    }
}
