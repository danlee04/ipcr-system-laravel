<?php

namespace Tests\Feature\Ipcr;

use App\Enums\IpcrMode;
use App\Enums\IpcrStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\IpcrPeriod;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A deadline that is only decoration.
 *
 * The period has always carried a submission deadline and the dashboard has
 * always shown it, but nothing ever happened when it passed: an IPCR handed in
 * three weeks late looked exactly like one handed in early. Now it says so.
 *
 * Marked, not blocked. In a hospital a hard block turns into a phone call to
 * the administrator, and unpicking that afterwards is worse than an honest
 * record of who was late.
 */
class LateSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function period(?string $deadline): IpcrPeriod
    {
        return IpcrPeriod::factory()->create([
            'status'              => 'open',
            'name'                => 'January - June 2026',
            'submission_deadline' => $deadline,
        ]);
    }

    private function ipcr(IpcrPeriod $period, ?string $submittedAt, array $overrides = []): Ipcr
    {
        return Ipcr::factory()->create(array_merge([
            'ipcr_period_id' => $period->id,
            'status'         => $submittedAt === null ? IpcrStatus::Draft : IpcrStatus::Submitted,
            'submitted_at'   => $submittedAt,
        ], $overrides));
    }

    // -----------------------------------------------------------------
    // What counts as late
    // -----------------------------------------------------------------

    public function test_handed_in_after_the_deadline_is_late(): void
    {
        $ipcr = $this->ipcr($this->period('2026-07-15'), '2026-07-18 09:00');

        $this->assertTrue($ipcr->isLate());
        $this->assertSame(3, $ipcr->daysLate());
    }

    /** The deadline is a day, not a moment - the whole of it counts. */
    public function test_handed_in_on_the_deadline_itself_is_on_time(): void
    {
        $ipcr = $this->ipcr($this->period('2026-07-15'), '2026-07-15 23:30');

        $this->assertFalse($ipcr->isLate());
        $this->assertSame(0, $ipcr->daysLate());
    }

    public function test_a_period_with_no_deadline_can_never_be_late(): void
    {
        $this->assertFalse($this->ipcr($this->period(null), '2030-01-01 09:00')->isLate());
    }

    public function test_a_draft_nobody_has_handed_in_is_not_late(): void
    {
        $this->assertFalse($this->ipcr($this->period('2020-01-01'), null)->isLate());
    }

    /**
     * Read against the deadline as it stands, not frozen at submission.
     *
     * Extending a period is how an office forgives the people it was extended
     * for. A mark stamped at submission would leave them late for a date that
     * no longer exists.
     */
    public function test_extending_the_deadline_forgives_whoever_it_covers(): void
    {
        $period = $this->period('2026-07-15');
        $ipcr = $this->ipcr($period, '2026-07-18 09:00');

        $this->assertTrue($ipcr->isLate());

        $period->update(['submission_deadline' => '2026-07-31']);

        $this->assertFalse($ipcr->fresh()->isLate());
    }

    // -----------------------------------------------------------------
    // Submitting late still works
    // -----------------------------------------------------------------

    public function test_a_late_submission_is_accepted_and_says_so(): void
    {
        $this->seed(RoleSeeder::class);

        $division = Division::factory()->create();
        $section = Section::factory()->create(['division_id' => $division->id]);
        $head = Employee::factory()->create(['section_id' => $section->id]);
        $divisionHead = Employee::factory()->create(['division_id' => $division->id]);
        $section->update(['section_head_employee_id' => $head->id]);
        $division->update(['division_head_employee_id' => $divisionHead->id]);

        $user = User::factory()->create();
        $owner = Employee::factory()->create([
            'user_id' => $user->id, 'section_id' => $section->id, 'division_id' => $division->id,
        ]);

        $period = $this->period(now()->subWeek()->toDateString());
        $ipcr = Ipcr::factory()->create([
            'employee_id' => $owner->id, 'ipcr_period_id' => $period->id,
            'status' => IpcrStatus::Draft, 'mode' => IpcrMode::TargetsOnly,
        ]);
        IpcrItem::factory()->create(['ipcr_id' => $ipcr->id, 'weight' => 100]);

        $this->actingAs($user->fresh())
            ->post(route('ipcrs.submit', $ipcr))
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, 'past the deadline'));

        $ipcr = $ipcr->fresh();
        $this->assertSame(IpcrStatus::Submitted, $ipcr->status);
        $this->assertTrue($ipcr->isLate(), 'Accepted, and recorded as late.');
    }

    // -----------------------------------------------------------------
    // Where it shows
    // -----------------------------------------------------------------

    public function test_the_ipcr_page_says_it(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $ipcr = $this->ipcr($this->period('2026-07-15'), '2026-07-18 09:00', [
            'employee_id' => $employee->id,
        ]);

        $this->actingAs($user->fresh())
            ->get(route('ipcrs.show', $ipcr))
            ->assertOk()
            ->assertSee('3 days late');
    }

    public function test_the_period_summary_counts_them(): void
    {
        $this->seed(RoleSeeder::class);
        $period = $this->period('2026-07-15');

        $this->ipcr($period, '2026-07-18 09:00', ['employee_id' => Employee::factory()->create()->id]);
        $this->ipcr($period, '2026-07-01 09:00', ['employee_id' => Employee::factory()->create()->id]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.summary.index', ['period' => $period->id]))
            ->assertOk()
            ->assertSee('1 late');
    }

    public function test_nobody_late_means_no_mention_of_it(): void
    {
        $this->seed(RoleSeeder::class);
        $period = $this->period('2026-07-15');
        $this->ipcr($period, '2026-07-01 09:00', ['employee_id' => Employee::factory()->create()->id]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.summary.index', ['period' => $period->id]))
            ->assertOk()
            ->assertDontSee('0 late');
    }

    public function test_the_download_carries_the_days(): void
    {
        $this->seed(RoleSeeder::class);
        $period = $this->period('2026-07-15');
        $this->ipcr($period, '2026-07-18 09:00', [
            'employee_id' => Employee::factory()->create(['last_name' => 'Santos'])->id,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $csv = $this->actingAs($admin)
            ->get(route('admin.summary.export', ['period' => $period->id]))
            ->streamedContent();

        $this->assertStringContainsString('Days Late', $csv);
        $this->assertMatchesRegularExpression('/Santos.*,3\r?\n/', $csv);
    }
}
