<?php

namespace Tests\Feature\Admin;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalStage;
use App\Enums\FunctionCategory;
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
 * The administrative escape hatches on an IPCR.
 *
 * Two things the ordinary flow cannot do:
 *
 *   1. Route an IPCR that IpcrRoutingService refuses to route. The Chief of
 *      Hospital has nobody above them, so their own IPCR has no automatic
 *      chain at all - without this screen they can never submit.
 *   2. Undo an approval. Approved is otherwise a one-way door, and a wrong
 *      mark found after signing would only be fixable in the database.
 *
 * Both are recorded in the same audit trail as every other action, because an
 * override that leaves no trace is worse than no override.
 */
class IpcrChainOverrideTest extends TestCase
{
    use RefreshDatabase;

    private Employee $owner;

    private Employee $assessor;

    private Employee $finalApprover;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->owner = $this->employeeWithLogin();
        $this->assessor = $this->employeeWithLogin();
        $this->finalApprover = $this->employeeWithLogin();
    }

    private function employeeWithLogin(array $attributes = []): Employee
    {
        return Employee::factory()->create($attributes + ['user_id' => User::factory()->create()->id]);
    }

    /** An administrator with no employee record - the seeded one has none. */
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function hr(): User
    {
        $user = User::factory()->create();
        $user->assignRole('hr');

        return $user;
    }

    private function draftIpcr(array $overrides = []): Ipcr
    {
        $ipcr = Ipcr::factory()->create($overrides + ['employee_id' => $this->owner->id]);

        // Accomplished, and weighted to a full 100: the submit guards must not
        // be what stops these IPCRs, or a routing test would pass without ever
        // reaching the routing.
        IpcrItem::factory()->accomplished()->rated()->create([
            'ipcr_id'  => $ipcr->id,
            'category' => FunctionCategory::Core,
            'weight'   => 100,
        ]);

        return $ipcr->fresh();
    }

    private function approvedIpcr(): Ipcr
    {
        $ipcr = $this->draftIpcr([
            'status'                     => IpcrStatus::Approved,
            'assessor_employee_id'       => $this->assessor->id,
            'final_approver_employee_id' => $this->finalApprover->id,
            'submitted_at'               => now()->subDays(3),
            'assessed_at'                => now()->subDays(2),
            'approved_at'                => now()->subDay(),
            'core_rating'                => 4.5,
            'core_weight'                => 100,
            'final_numerical_rating'     => 4.5,
            'final_adjectival_rating'    => 'Outstanding',
        ]);

        $ipcr->items->each->update([
            'quality_rating'    => 4.5,
            'efficiency_rating' => 4.5,
            'timeliness_rating' => 4.5,
        ]);

        return $ipcr->fresh();
    }

    private function chainPayload(array $overrides = []): array
    {
        return $overrides + [
            'assessor_employee_id'       => $this->assessor->id,
            'final_approver_employee_id' => $this->finalApprover->id,
            'reason'                     => 'The Chief of Hospital has no automatic chain.',
        ];
    }

    /**
     * An employee the org chart can route on its own: in a section that has a
     * head, in a division that has a head.
     */
    private function employeeWithWorkingChain(): Employee
    {
        $division = Division::factory()->create(['division_head_employee_id' => $this->finalApprover->id]);
        $section = Section::factory()->create([
            'division_id'              => $division->id,
            'section_head_employee_id' => $this->assessor->id,
        ]);

        return $this->employeeWithLogin([
            'section_id'  => $section->id,
            'division_id' => $division->id,
        ]);
    }

    // -----------------------------------------------------------------
    // The override is an exception, not a step
    // -----------------------------------------------------------------

    /**
     * Nobody sets an approver by hand where the org chart already answers the
     * question. Leaving the door open invites hand-routing to become the
     * habit, and a hand-routed IPCR stops following a change of head.
     */
    public function test_an_ipcr_that_routes_itself_cannot_be_rerouted(): void
    {
        $ipcr = $this->draftIpcr(['employee_id' => $this->employeeWithWorkingChain()->id]);

        $this->actingAs($this->admin())
            ->put(route('admin.ipcrs.chain', $ipcr), $this->chainPayload())
            ->assertForbidden();

        $this->assertNull($ipcr->fresh()->chain_overridden_at);
    }

    public function test_the_list_does_not_offer_approvers_when_routing_is_automatic(): void
    {
        $ipcr = $this->draftIpcr(['employee_id' => $this->employeeWithWorkingChain()->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.ipcrs.index'))
            ->assertOk()
            ->assertDontSee(route('admin.ipcrs.chain', $ipcr), false);
    }

    /**
     * A chain set by hand: all three columns together, which is the only way
     * the controller ever writes them.
     */
    private function overriddenIpcr(array $overrides = []): Ipcr
    {
        return $this->draftIpcr($overrides + [
            'assessor_employee_id'       => $this->assessor->id,
            'final_approver_employee_id' => $this->finalApprover->id,
            'chain_overridden_at'        => now(),
        ]);
    }

    /** A chain already set by hand must stay correctable. */
    public function test_an_override_already_in_place_can_still_be_corrected(): void
    {
        $ipcr = $this->overriddenIpcr(['employee_id' => $this->employeeWithWorkingChain()->id]);

        $this->actingAs($this->admin())
            ->put(route('admin.ipcrs.chain', $ipcr), $this->chainPayload())
            ->assertRedirect();
    }

    /**
     * Without this the override is a one-way door of its own: an IPCR routed
     * by hand while a section had no head would keep ignoring the head
     * appointed afterwards, forever.
     */
    public function test_an_override_can_be_handed_back_to_automatic_routing(): void
    {
        $ipcr = $this->overriddenIpcr(['employee_id' => $this->employeeWithWorkingChain()->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.ipcrs.chain', $ipcr), ['reason' => 'The section has a head again.'])
            ->assertRedirect();

        $ipcr->refresh();

        $this->assertNull($ipcr->chain_overridden_at);
        $this->assertFalse($ipcr->hasOverriddenChain());

        $approval = $ipcr->approvals()->first();
        $this->assertSame(ApprovalAction::Rerouted, $approval->action);
        $this->assertStringContainsString('automatic', $approval->remarks);
        $this->assertStringContainsString('The section has a head again.', $approval->remarks);
    }

    public function test_handing_back_requires_a_reason(): void
    {
        $ipcr = $this->overriddenIpcr();

        $this->actingAs($this->admin())
            ->delete(route('admin.ipcrs.chain', $ipcr), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertNotNull($ipcr->fresh()->chain_overridden_at);
    }

    public function test_an_ordinary_employee_cannot_hand_a_chain_back(): void
    {
        $ipcr = $this->overriddenIpcr();

        $this->actingAs($this->owner->user)
            ->delete(route('admin.ipcrs.chain', $ipcr), ['reason' => 'Let me out of this.'])
            ->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Changing the chain
    // -----------------------------------------------------------------

    public function test_an_administrator_can_set_the_approval_chain(): void
    {
        $ipcr = $this->draftIpcr();

        $this->actingAs($this->admin())
            ->put(route('admin.ipcrs.chain', $ipcr), $this->chainPayload())
            ->assertRedirect();

        $ipcr->refresh();

        $this->assertSame($this->assessor->id, $ipcr->assessor_employee_id);
        $this->assertSame($this->finalApprover->id, $ipcr->final_approver_employee_id);
        $this->assertNotNull($ipcr->chain_overridden_at);
    }

    public function test_hr_can_also_set_the_approval_chain(): void
    {
        $ipcr = $this->draftIpcr();

        $this->actingAs($this->hr())
            ->put(route('admin.ipcrs.chain', $ipcr), $this->chainPayload())
            ->assertRedirect();

        $this->assertSame($this->assessor->id, $ipcr->fresh()->assessor_employee_id);
    }

    public function test_the_change_is_recorded_in_the_approval_history(): void
    {
        $ipcr = $this->draftIpcr();

        $this->actingAs($this->admin())
            ->put(route('admin.ipcrs.chain', $ipcr), $this->chainPayload([
                'reason' => 'Section head is on leave.',
            ]));

        $approval = $ipcr->approvals()->first();

        $this->assertNotNull($approval);
        $this->assertSame(ApprovalAction::Rerouted, $approval->action);
        $this->assertSame(ApprovalStage::Administrative, $approval->stage);
        $this->assertStringContainsString('Section head is on leave.', $approval->remarks);
    }

    /**
     * The seeded administrator has no employee record, so an audit row that
     * insisted on one could never be written for the very actions this screen
     * exists to perform.
     */
    public function test_the_change_is_attributed_to_an_administrator_who_has_no_employee_record(): void
    {
        $admin = $this->admin();
        $ipcr = $this->draftIpcr();

        $this->actingAs($admin)->put(route('admin.ipcrs.chain', $ipcr), $this->chainPayload());

        $approval = $ipcr->approvals()->first();

        $this->assertNull($approval->approver_employee_id);
        $this->assertSame($admin->id, $approval->acted_by_user_id);
        $this->assertStringContainsString($admin->name, $approval->actorName());
    }

    public function test_the_ratee_cannot_be_named_as_their_own_approver(): void
    {
        $ipcr = $this->draftIpcr();

        $this->actingAs($this->admin())
            ->put(route('admin.ipcrs.chain', $ipcr), $this->chainPayload([
                'assessor_employee_id' => $this->owner->id,
            ]))
            ->assertSessionHasErrors('assessor_employee_id');

        $this->assertNull($ipcr->fresh()->assessor_employee_id);
    }

    public function test_one_person_may_hold_both_slots(): void
    {
        // The Division Head case already routes the Chief of Hospital into
        // both, so this must stay legal.
        $ipcr = $this->draftIpcr();

        $this->actingAs($this->admin())
            ->put(route('admin.ipcrs.chain', $ipcr), $this->chainPayload([
                'final_approver_employee_id' => $this->assessor->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->assessor->id, $ipcr->fresh()->final_approver_employee_id);
    }

    public function test_an_inactive_employee_cannot_be_named_as_an_approver(): void
    {
        $retired = Employee::factory()->create(['is_active' => false]);
        $ipcr = $this->draftIpcr();

        $this->actingAs($this->admin())
            ->put(route('admin.ipcrs.chain', $ipcr), $this->chainPayload([
                'assessor_employee_id' => $retired->id,
            ]))
            ->assertSessionHasErrors('assessor_employee_id');
    }

    public function test_a_reason_is_required(): void
    {
        $ipcr = $this->draftIpcr();

        $this->actingAs($this->admin())
            ->put(route('admin.ipcrs.chain', $ipcr), $this->chainPayload(['reason' => '']))
            ->assertSessionHasErrors('reason');
    }

    public function test_an_approved_ipcr_cannot_be_rerouted(): void
    {
        $ipcr = $this->approvedIpcr();

        $this->actingAs($this->admin())
            ->put(route('admin.ipcrs.chain', $ipcr), $this->chainPayload())
            ->assertForbidden();
    }

    public function test_an_ordinary_employee_cannot_change_the_chain(): void
    {
        $ipcr = $this->draftIpcr();

        $this->actingAs($this->owner->user)
            ->put(route('admin.ipcrs.chain', $ipcr), $this->chainPayload())
            ->assertForbidden();
    }

    // -----------------------------------------------------------------
    // The Chief of Hospital - the reason this screen exists
    // -----------------------------------------------------------------

    public function test_the_chief_of_hospital_cannot_submit_without_an_override(): void
    {
        $chief = $this->employeeWithLogin(['is_chief_of_hospital' => true]);
        $ipcr = $this->draftIpcr(['employee_id' => $chief->id]);

        $this->actingAs($chief->user)
            ->post(route('ipcrs.submit', $ipcr))
            ->assertSessionHas('error');

        $this->assertSame(IpcrStatus::Draft, $ipcr->fresh()->status);
    }

    public function test_the_chief_of_hospital_can_submit_once_an_administrator_has_set_the_chain(): void
    {
        $chief = $this->employeeWithLogin(['is_chief_of_hospital' => true]);
        $ipcr = $this->draftIpcr(['employee_id' => $chief->id]);

        $this->actingAs($this->admin())
            ->put(route('admin.ipcrs.chain', $ipcr), $this->chainPayload());

        $this->actingAs($chief->user)->post(route('ipcrs.submit', $ipcr));

        $ipcr->refresh();

        $this->assertSame(IpcrStatus::Submitted, $ipcr->status);
        $this->assertSame($this->assessor->id, $ipcr->assessor_employee_id);
        $this->assertSame($this->finalApprover->id, $ipcr->final_approver_employee_id);
    }

    /**
     * Without an override, submission must still resolve the chain from the
     * org chart. The override is an exception, not a replacement.
     */
    public function test_submission_still_routes_automatically_when_there_is_no_override(): void
    {
        $ipcr = $this->draftIpcr([
            'assessor_employee_id'       => $this->assessor->id,
            'final_approver_employee_id' => $this->finalApprover->id,
        ]);

        // The owner has no section, so automatic routing fails and the stale
        // stamp must not be used as a substitute for it.
        $this->actingAs($this->owner->user)
            ->post(route('ipcrs.submit', $ipcr))
            ->assertSessionHas('error');

        $this->assertSame(IpcrStatus::Draft, $ipcr->fresh()->status);
    }

    // -----------------------------------------------------------------
    // Reopening an approved IPCR
    // -----------------------------------------------------------------

    public function test_an_administrator_can_reopen_an_approved_ipcr_for_reassessment(): void
    {
        $ipcr = $this->approvedIpcr();

        $this->actingAs($this->admin())
            ->post(route('admin.ipcrs.reopen', $ipcr), [
                'target' => 'assessment',
                'reason' => 'Timeliness mark was keyed in wrong.',
            ])
            ->assertRedirect();

        $ipcr->refresh();

        $this->assertSame(IpcrStatus::Submitted, $ipcr->status);
        $this->assertNull($ipcr->approved_at);
        $this->assertNull($ipcr->assessed_at);
    }

    public function test_reopening_to_the_employee_returns_it_for_revision(): void
    {
        $ipcr = $this->approvedIpcr();

        $this->actingAs($this->hr())
            ->post(route('admin.ipcrs.reopen', $ipcr), [
                'target' => 'employee',
                'reason' => 'Wrong targets were committed.',
            ]);

        $ipcr->refresh();

        $this->assertSame(IpcrStatus::Returned, $ipcr->status);
        $this->assertTrue($ipcr->isEditableByOwner());
    }

    /**
     * A reopened IPCR must not keep showing the rating it was approved with.
     * The number is recomputed when it is approved again; until then it is not
     * a fact about this IPCR.
     */
    public function test_reopening_clears_the_final_rating(): void
    {
        $ipcr = $this->approvedIpcr();

        $this->actingAs($this->admin())->post(route('admin.ipcrs.reopen', $ipcr), [
            'target' => 'assessment',
            'reason' => 'Marks disputed.',
        ]);

        $ipcr->refresh();

        $this->assertNull($ipcr->final_numerical_rating);
        $this->assertNull($ipcr->final_adjectival_rating);
        $this->assertNull($ipcr->core_rating);
    }

    /** Clearing the rating would erase it, so the history has to carry it. */
    public function test_the_rating_it_was_approved_with_is_kept_in_the_history(): void
    {
        $ipcr = $this->approvedIpcr();

        $this->actingAs($this->admin())->post(route('admin.ipcrs.reopen', $ipcr), [
            'target' => 'assessment',
            'reason' => 'Marks disputed.',
        ]);

        $approval = $ipcr->approvals()->first();

        $this->assertSame(ApprovalAction::Reopened, $approval->action);
        $this->assertStringContainsString('4.5', $approval->remarks);
        $this->assertStringContainsString('Outstanding', $approval->remarks);
        $this->assertStringContainsString('Marks disputed.', $approval->remarks);
    }

    /** The marks stay: an assessor correcting one line should not redo twenty. */
    public function test_reopening_keeps_the_marks_on_each_line(): void
    {
        $ipcr = $this->approvedIpcr();

        $this->actingAs($this->admin())->post(route('admin.ipcrs.reopen', $ipcr), [
            'target' => 'assessment',
            'reason' => 'Marks disputed.',
        ]);

        $this->assertSame('4.50', $ipcr->fresh()->items->first()->quality_rating);
    }

    public function test_an_ipcr_that_is_not_approved_cannot_be_reopened(): void
    {
        $ipcr = $this->draftIpcr();

        $this->actingAs($this->admin())
            ->post(route('admin.ipcrs.reopen', $ipcr), [
                'target' => 'assessment',
                'reason' => 'Nothing to reopen.',
            ])
            ->assertForbidden();
    }

    public function test_reopening_requires_a_reason(): void
    {
        $ipcr = $this->approvedIpcr();

        $this->actingAs($this->admin())
            ->post(route('admin.ipcrs.reopen', $ipcr), ['target' => 'assessment', 'reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(IpcrStatus::Approved, $ipcr->fresh()->status);
    }

    public function test_reopening_rejects_an_unknown_target(): void
    {
        $ipcr = $this->approvedIpcr();

        $this->actingAs($this->admin())
            ->post(route('admin.ipcrs.reopen', $ipcr), ['target' => 'nowhere', 'reason' => 'Because.'])
            ->assertSessionHasErrors('target');
    }

    public function test_an_ordinary_employee_cannot_reopen_their_own_ipcr(): void
    {
        $ipcr = $this->approvedIpcr();

        $this->actingAs($this->owner->user)
            ->post(route('admin.ipcrs.reopen', $ipcr), [
                'target' => 'employee',
                'reason' => 'I want a better rating.',
            ])
            ->assertForbidden();

        $this->assertSame(IpcrStatus::Approved, $ipcr->fresh()->status);
    }

    /**
     * AdminAccessTest covers the group's GET routes by name. These two take a
     * model, so they cannot go in that data provider - and an unprotected
     * write route is worse than an unprotected read one.
     */
    public function test_a_guest_is_sent_to_login(): void
    {
        $ipcr = $this->draftIpcr();

        $this->put(route('admin.ipcrs.chain', $ipcr), $this->chainPayload())
            ->assertRedirect(route('login'));

        $this->post(route('admin.ipcrs.reopen', $ipcr), ['target' => 'assessment', 'reason' => 'x'])
            ->assertRedirect(route('login'));
    }

    // -----------------------------------------------------------------
    // The screen itself
    // -----------------------------------------------------------------

    public function test_the_all_ipcrs_list_offers_both_actions(): void
    {
        // One of each, so both buttons have a row to appear on: they are
        // mutually exclusive by status.
        $draft = $this->draftIpcr();
        $approved = $this->approvedIpcr();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.ipcrs.index'))
            ->assertOk()
            ->assertSee('Approvers')
            ->assertSee('Reopen');

        // The button and the modal it opens are wired by name. Asserting the
        // labels alone would still pass if the dispatch missed its target,
        // leaving a button that does nothing at all.
        // The button dispatches a name and the modal listens for that same
        // name. Asserting the labels alone would still pass with the two
        // sides out of step, leaving a button that does nothing at all.
        $response->assertSee("open-modal', 'chain-{$draft->id}", false)
            ->assertSee('open-modal.window="$event.detail == \'chain-' . $draft->id . '\'', false)
            ->assertSee(route('admin.ipcrs.chain', $draft), false);

        $response->assertSee("open-modal', 'reopen-{$approved->id}", false)
            ->assertSee('open-modal.window="$event.detail == \'reopen-' . $approved->id . '\'', false)
            ->assertSee(route('admin.ipcrs.reopen', $approved), false);
    }

    /** Each action appears only where its policy allows it. */
    public function test_the_two_actions_never_appear_on_the_same_row(): void
    {
        $approved = $this->approvedIpcr();

        $this->actingAs($this->admin())
            ->get(route('admin.ipcrs.index'))
            ->assertOk()
            ->assertDontSee(route('admin.ipcrs.chain', $approved), false)
            ->assertSee(route('admin.ipcrs.reopen', $approved), false);
    }

    /**
     * The ratee must not be offered as their own approver.
     *
     * Checked inside the two selects rather than across the page: the ratee's
     * own id and name appear legitimately elsewhere on the row, so a
     * whole-page assertion would be measuring the wrong thing.
     */
    public function test_the_ratee_is_left_out_of_the_approver_pickers(): void
    {
        $this->draftIpcr();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.ipcrs.index'))
            ->assertOk()
            ->getContent();

        preg_match_all('/<select name="(assessor_employee_id|final_approver_employee_id)".*?<\/select>/s', $html, $matches);

        $this->assertCount(2, $matches[0], 'Both approver pickers should be on the page.');

        foreach ($matches[0] as $select) {
            $this->assertStringNotContainsString('value="' . $this->owner->id . '"', $select);
            $this->assertStringContainsString('value="' . $this->assessor->id . '"', $select);
        }
    }

    public function test_an_open_period_is_not_needed_to_reopen(): void
    {
        // Ratings are usually disputed after the period has closed.
        IpcrPeriod::query()->update(['status' => 'closed']);

        $ipcr = $this->approvedIpcr();

        $this->actingAs($this->admin())
            ->post(route('admin.ipcrs.reopen', $ipcr), [
                'target' => 'assessment',
                'reason' => 'Found after the period closed.',
            ])
            ->assertRedirect();

        $this->assertSame(IpcrStatus::Submitted, $ipcr->fresh()->status);
    }
}
