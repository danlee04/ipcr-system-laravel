<?php

namespace Tests\Feature;

use App\Enums\IpcrStatus;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The rail down the side of the administrator's dashboard.
 *
 * For HR and administrators only. An employee's landing page answers one
 * question - what should I do next - and a column of shortcuts and other
 * people's business beside it answers nothing they asked.
 *
 * It starts below the personal cards rather than level with them, so it runs
 * alongside the hospital-wide figures: the part of the page long enough to
 * want a companion.
 */
class DashboardRailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function html(User $user): string
    {
        return $this->actingAs($user->fresh())->get('/dashboard')->assertOk()->getContent();
    }

    private function rail(User $user): string
    {
        $html = $this->html($user);
        $start = strpos($html, 'data-dashboard-rail');

        $this->assertNotFalse($start, 'No rail on the dashboard.');

        return substr($html, $start);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);
        $user->assignRole('admin');

        return $user;
    }

    private function submitted(string $first, string $last, ?IpcrPeriod $period = null): Ipcr
    {
        return Ipcr::factory()->create([
            'employee_id'    => Employee::factory()->create(['first_name' => $first, 'last_name' => $last])->id,
            'ipcr_period_id' => ($period ?? IpcrPeriod::factory()->create(['status' => 'open']))->id,
            'status'         => IpcrStatus::Submitted,
            'submitted_at'   => now(),
        ]);
    }

    // -----------------------------------------------------------------
    // Who gets one
    // -----------------------------------------------------------------

    public function test_an_ordinary_employee_has_no_rail(): void
    {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);

        $this->assertStringNotContainsString('data-dashboard-rail', $this->html($user));
    }

    public function test_hr_and_administrators_get_one(): void
    {
        $this->assertStringContainsString('data-dashboard-rail', $this->html($this->admin()));
    }

    /**
     * Below the cards and below the strip of figures.
     *
     * Both of those read across the whole page - that is the shape of a row
     * of numbers - and the rail belongs against the column of panels that
     * follows them.
     */
    public function test_it_starts_below_the_cards_and_the_figures(): void
    {
        $html = $this->html($this->admin());

        $rail = strpos($html, 'data-dashboard-rail');

        $this->assertLessThan($rail, strpos($html, 'My IPCR'), 'The rail starts level with the cards.');
        $this->assertLessThan($rail, strpos($html, 'Total IPCRs'), 'The rail starts level with the figures.');
        $this->assertLessThan($rail, strpos($html, 'Approved'), 'The rail starts inside the figures.');

        // Its own column comes last in the source; the grid puts it on the
        // right. What matters is that the strip above is already finished.
        $this->assertGreaterThan(strpos($html, 'Status distribution'), $rail);
    }

    // -----------------------------------------------------------------
    // Recent submissions
    // -----------------------------------------------------------------

    public function test_recent_activity_is_the_sheets_that_were_submitted(): void
    {
        $this->submitted('Mary Jane', 'Guico');

        $this->assertStringContainsString('Mary Jane Guico', $this->rail($this->admin()));
    }

    /** A draft has never left the employee's hands, so nothing happened. */
    public function test_a_draft_is_not_activity(): void
    {
        Ipcr::factory()->create([
            'employee_id'    => Employee::factory()->create(['first_name' => 'Still', 'last_name' => 'Drafting'])->id,
            'ipcr_period_id' => IpcrPeriod::factory()->create(['status' => 'open'])->id,
            'status'         => IpcrStatus::Draft,
            'submitted_at'   => null,
        ]);

        $this->assertStringNotContainsString('Still Drafting', $this->rail($this->admin()));
    }

    /**
     * Newest first, by when it was sent.
     *
     * Not by when it was last touched: an assessor typing a mark moves that
     * and says nothing about who is getting their work in.
     */
    public function test_the_newest_submission_leads(): void
    {
        $period = IpcrPeriod::factory()->create(['status' => 'open']);

        $this->submitted('Older', 'Sender', $period)->update(['submitted_at' => now()->subDays(3)]);
        $this->submitted('Newer', 'Sender', $period)->update(['submitted_at' => now()]);

        $rail = $this->rail($this->admin());

        $this->assertLessThan(
            strpos($rail, 'Older Sender'),
            strpos($rail, 'Newer Sender'),
        );
    }

    public function test_it_says_so_when_nothing_has_been_submitted(): void
    {
        $this->assertStringContainsString('Nothing submitted yet', $this->rail($this->admin()));
    }

    // -----------------------------------------------------------------
    // The rest of the rail
    // -----------------------------------------------------------------

    public function test_it_offers_the_screens_an_administrator_lives_in(): void
    {
        $rail = $this->rail($this->admin());

        foreach (['admin.ipcrs.index', 'admin.summary.index', 'admin.employees.index'] as $name) {
            $this->assertStringContainsString(route($name), $rail, "No shortcut to {$name}.");
        }
    }

    /** Where the open period stands: the deadline, and how much is in. */
    public function test_it_says_where_the_open_period_stands(): void
    {
        IpcrPeriod::factory()->create([
            'name'                => 'July - December',
            'status'              => 'open',
            'submission_deadline' => now()->addDays(12),
        ]);

        $rail = $this->rail($this->admin());

        $this->assertStringContainsString('July - December', $rail);
        $this->assertStringContainsString('12 days left', $rail);
    }
}
