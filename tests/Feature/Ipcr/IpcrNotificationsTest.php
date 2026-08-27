<?php

namespace Tests\Feature\Ipcr;

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
 * Telling people their IPCR has moved.
 *
 * Until now nothing did. An IPCR landed in a Section Head's inbox in silence,
 * and one returned for revision sat in the owner's list looking much as it did
 * before - so the only way to find out anything had happened was to go and
 * look. In a hospital of several hundred people that chasing is the real work,
 * and it is exactly what a system can take off HR.
 *
 * In the app, not by email: no mail server is configured, and a notification
 * that silently fails to send is worse than none.
 */
class IpcrNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $sectionHead;

    private User $divisionHead;

    private IpcrPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $division = Division::factory()->create();
        $section = Section::factory()->create(['division_id' => $division->id]);

        $this->sectionHead = $this->employeeUser(['section_id' => $section->id]);
        $this->divisionHead = $this->employeeUser(['division_id' => $division->id]);

        $section->update(['section_head_employee_id' => $this->sectionHead->employee->id]);
        $division->update(['division_head_employee_id' => $this->divisionHead->employee->id]);

        $this->owner = $this->employeeUser([
            'section_id' => $section->id, 'division_id' => $division->id,
        ]);

        $this->period = IpcrPeriod::factory()->create(['status' => 'open', 'name' => 'January - June 2026']);
    }

    private function employeeUser(array $attributes = []): User
    {
        $user = User::factory()->create();
        Employee::factory()->create(array_merge(['user_id' => $user->id], $attributes));

        return $user->fresh();
    }

    private function draft(): Ipcr
    {
        $ipcr = Ipcr::factory()->create([
            'employee_id'    => $this->owner->employee->id,
            'ipcr_period_id' => $this->period->id,
            'status'         => IpcrStatus::Draft,
            'mode'           => \App\Enums\IpcrMode::TargetsOnly,
        ]);

        IpcrItem::factory()->create(['ipcr_id' => $ipcr->id, 'weight' => 100]);

        return $ipcr;
    }

    private function submitted(): Ipcr
    {
        $ipcr = $this->draft();
        $this->actingAs($this->owner)->post(route('ipcrs.submit', $ipcr));

        return $ipcr->fresh();
    }

    /** @return array<int, string> the messages this user has been sent */
    private function messagesFor(User $user): array
    {
        return $user->fresh()->notifications
            ->map(fn ($notification): string => $notification->data['message'])
            ->all();
    }

    // -----------------------------------------------------------------
    // Each step tells the person it now waits on
    // -----------------------------------------------------------------

    public function test_submitting_tells_the_assessor(): void
    {
        $this->submitted();

        $messages = $this->messagesFor($this->sectionHead);

        $this->assertCount(1, $messages);
        $this->assertStringContainsString($this->owner->employee->full_name, $messages[0]);
        $this->assertStringContainsString('January - June 2026', $messages[0]);
    }

    /** Only the person it waits on. Everyone else has nothing to do. */
    public function test_nobody_else_is_told(): void
    {
        $this->submitted();

        $this->assertCount(0, $this->messagesFor($this->divisionHead));
        $this->assertCount(0, $this->messagesFor($this->owner));
    }

    public function test_completing_the_assessment_tells_the_final_approver(): void
    {
        $ipcr = $this->submitted();

        $this->actingAs($this->sectionHead)->put(route('ipcrs.ratings.update', $ipcr), [
            'ratings' => [$ipcr->items->first()->id => ['quality' => 4, 'efficiency' => 4, 'timeliness' => 4]],
        ]);
        $this->actingAs($this->sectionHead)->post(route('ipcrs.assess', $ipcr));

        $this->assertCount(1, $this->messagesFor($this->divisionHead));
    }

    public function test_returning_it_tells_the_owner_why(): void
    {
        $ipcr = $this->submitted();

        $this->actingAs($this->sectionHead)->post(route('ipcrs.return', $ipcr), [
            'remarks' => 'The weights do not match your actual duties.',
        ]);

        $messages = $this->messagesFor($this->owner);

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('The weights do not match your actual duties.', $messages[0]);
    }

    public function test_approving_it_tells_the_owner(): void
    {
        $ipcr = $this->submitted();

        $this->actingAs($this->sectionHead)->put(route('ipcrs.ratings.update', $ipcr), [
            'ratings' => [$ipcr->items->first()->id => ['quality' => 4, 'efficiency' => 4, 'timeliness' => 4]],
        ]);
        $this->actingAs($this->sectionHead)->post(route('ipcrs.assess', $ipcr));
        $this->actingAs($this->divisionHead)->post(route('ipcrs.approve', $ipcr));

        $messages = $this->messagesFor($this->owner);

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('approved', $messages[0]);
    }

    /**
     * Plenty of employees have no account - they are on the roll so their
     * office is complete, not so they can sign in. Having nobody to tell must
     * not stop the IPCR moving.
     */
    public function test_an_approver_with_no_account_does_not_stop_the_submission(): void
    {
        $this->sectionHead->employee->update(['user_id' => null]);

        $ipcr = $this->draft();

        $this->actingAs($this->owner)
            ->post(route('ipcrs.submit', $ipcr))
            ->assertSessionHasNoErrors();

        $this->assertSame(IpcrStatus::Submitted, $ipcr->fresh()->status);
    }

    // -----------------------------------------------------------------
    // Reading them
    // -----------------------------------------------------------------

    public function test_the_page_lists_what_you_have_been_sent(): void
    {
        $this->submitted();

        $this->actingAs($this->sectionHead)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee($this->owner->employee->full_name);
    }

    public function test_opening_one_marks_it_read_and_goes_to_the_ipcr(): void
    {
        $ipcr = $this->submitted();
        $notification = $this->sectionHead->fresh()->notifications->first();

        $this->actingAs($this->sectionHead)
            ->get(route('notifications.show', $notification->id))
            ->assertRedirect(route('ipcrs.show', $ipcr));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_you_cannot_open_somebody_elses(): void
    {
        $this->submitted();
        $notification = $this->sectionHead->fresh()->notifications->first();

        $this->actingAs($this->divisionHead)
            ->get(route('notifications.show', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_they_can_all_be_marked_read_at_once(): void
    {
        $this->submitted();

        $this->actingAs($this->sectionHead)->post(route('notifications.read'));

        $this->assertCount(0, $this->sectionHead->fresh()->unreadNotifications);
    }

    /** The count is what makes anyone look. */
    public function test_the_unread_count_is_shown_in_the_sidebar(): void
    {
        $ipcr = $this->submitted();
        $this->sectionHead->notify(new \App\Notifications\IpcrSubmitted($ipcr));
        $this->sectionHead->notify(new \App\Notifications\IpcrSubmitted($ipcr));

        $this->assertSame('3', $this->sidebarBadgeFor($this->sectionHead));
    }

    /** Nothing waiting means no badge at all, not a nought. */
    public function test_no_badge_when_nothing_is_unread(): void
    {
        $this->assertNull($this->sidebarBadgeFor($this->owner));
    }

    /**
     * The count inside the Notifications link, or null when there is none.
     *
     * Read out of that one anchor rather than searched for across the page - a
     * bare "3" appears in a dozen places on a dashboard.
     */
    private function sidebarBadgeFor(User $user): ?string
    {
        $html = $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk()->getContent();

        $link = '#<a\s+href="' . preg_quote(route('notifications.index'), '#') . '".*?</a>#s';
        $this->assertMatchesRegularExpression($link, $html, 'The sidebar should link to the notifications.');

        preg_match($link, $html, $anchor);

        return preg_match('#<span[^>]*rounded-full bg-seal[^>]*>(\d+)</span>#s', $anchor[0], $badge)
            ? $badge[1]
            : null;
    }

    public function test_somebody_with_nothing_waiting_still_gets_a_page(): void
    {
        $this->actingAs($this->owner)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Nothing yet');
    }
}
