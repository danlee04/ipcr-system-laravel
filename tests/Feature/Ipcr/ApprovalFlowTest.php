<?php

namespace Tests\Feature\Ipcr;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalStage;
use App\Enums\FunctionCategory;
use App\Enums\IpcrStatus;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Submitted -> Assessed -> Approved, plus Returned from either stage.
 *
 * The assessor is the one who enters the Q/E/T marks; the final approver
 * confirms them. That split is what IpcrPolicy's docblock describes, and it is
 * why rating has its own route rather than going through `update`.
 */
class ApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    private Employee $owner;

    private Employee $assessor;

    private Employee $finalApprover;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->employeeWithLogin();
        $this->assessor = $this->employeeWithLogin();
        $this->finalApprover = $this->employeeWithLogin();
    }

    private function employeeWithLogin(): Employee
    {
        return Employee::factory()->create(['user_id' => User::factory()->create()->id]);
    }

    /** A submitted IPCR with one fully rateable core item. */
    private function submittedIpcr(array $overrides = []): Ipcr
    {
        $ipcr = Ipcr::factory()->submitted()->create(array_merge([
            'employee_id'                => $this->owner->id,
            'assessor_employee_id'       => $this->assessor->id,
            'final_approver_employee_id' => $this->finalApprover->id,
        ], $overrides));

        IpcrItem::factory()->create([
            'ipcr_id'  => $ipcr->id,
            'category' => FunctionCategory::Core,
            'weight'   => 100,
        ]);

        return $ipcr->fresh();
    }

    private function rate(Ipcr $ipcr, float $q = 5, float $e = 5, float $t = 5): void
    {
        $ipcr->items->each->update([
            'quality_rating'    => $q,
            'efficiency_rating' => $e,
            'timeliness_rating' => $t,
        ]);
    }

    // -----------------------------------------------------------------
    // The inbox
    // -----------------------------------------------------------------

    public function test_the_inbox_lists_ipcrs_awaiting_my_assessment(): void
    {
        $ipcr = $this->submittedIpcr();

        $this->actingAs($this->assessor->user)
            ->get(route('approvals.inbox'))
            ->assertOk()
            ->assertSee($ipcr->employee->full_name);
    }

    public function test_the_inbox_lists_ipcrs_awaiting_my_final_rating(): void
    {
        $ipcr = $this->submittedIpcr(['status' => IpcrStatus::Assessed]);

        $this->actingAs($this->finalApprover->user)
            ->get(route('approvals.inbox'))
            ->assertOk()
            ->assertSee($ipcr->employee->full_name);
    }

    public function test_the_inbox_does_not_list_other_peoples_work(): void
    {
        $this->submittedIpcr();
        $bystander = $this->employeeWithLogin();

        $this->actingAs($bystander->user)
            ->get(route('approvals.inbox'))
            ->assertOk()
            ->assertDontSee($this->owner->full_name);
    }

    public function test_a_user_without_an_employee_record_cannot_open_the_inbox(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('approvals.inbox'))
            ->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Entering the ratings
    // -----------------------------------------------------------------

    public function test_the_assessor_can_record_quality_efficiency_and_timeliness(): void
    {
        $ipcr = $this->submittedIpcr();
        $item = $ipcr->items->first();

        $this->actingAs($this->assessor->user)
            ->put(route('ipcrs.ratings.update', $ipcr), [
                'ratings' => [
                    $item->id => ['quality' => 5, 'efficiency' => 4, 'timeliness' => 3],
                ],
            ])
            ->assertRedirect(route('ipcrs.show', $ipcr));

        $item->refresh();
        $this->assertSame('5.00', $item->quality_rating);
        $this->assertSame('4.00', $item->efficiency_rating);
        $this->assertSame('3.00', $item->timeliness_rating);
        $this->assertSame('4.000', $item->average_rating, 'The item average must be stored, not only derived.');
    }

    public function test_the_owner_cannot_rate_their_own_ipcr(): void
    {
        $ipcr = $this->submittedIpcr();
        $item = $ipcr->items->first();

        $this->actingAs($this->owner->user)
            ->put(route('ipcrs.ratings.update', $ipcr), [
                'ratings' => [$item->id => ['quality' => 5, 'efficiency' => 5, 'timeliness' => 5]],
            ])
            ->assertForbidden();
    }

    public function test_a_rating_outside_one_to_five_is_rejected(): void
    {
        $ipcr = $this->submittedIpcr();
        $item = $ipcr->items->first();

        $this->actingAs($this->assessor->user)
            ->put(route('ipcrs.ratings.update', $ipcr), [
                'ratings' => [$item->id => ['quality' => 9, 'efficiency' => 5, 'timeliness' => 5]],
            ])
            ->assertSessionHasErrors('ratings.' . $item->id . '.quality');
    }

    public function test_ratings_cannot_be_written_onto_an_item_from_another_ipcr(): void
    {
        $ipcr = $this->submittedIpcr();
        $foreign = IpcrItem::factory()->create();

        $this->actingAs($this->assessor->user)
            ->put(route('ipcrs.ratings.update', $ipcr), [
                'ratings' => [$foreign->id => ['quality' => 1, 'efficiency' => 1, 'timeliness' => 1]],
            ])
            ->assertSessionHasErrors();

        $this->assertNull($foreign->fresh()->quality_rating);
    }

    // -----------------------------------------------------------------
    // Assessment
    // -----------------------------------------------------------------

    public function test_the_assessor_cannot_finish_while_an_item_is_unrated(): void
    {
        $ipcr = $this->submittedIpcr();

        $this->actingAs($this->assessor->user)->post(route('ipcrs.assess', $ipcr));

        $this->assertSame(IpcrStatus::Submitted, $ipcr->fresh()->status);
        $this->assertStringContainsString('rated', (string) session('error'));
    }

    public function test_completing_the_assessment_moves_the_ipcr_forward(): void
    {
        $ipcr = $this->submittedIpcr();
        $this->rate($ipcr, 5, 4, 3);

        $this->actingAs($this->assessor->user)->post(route('ipcrs.assess', $ipcr));

        $ipcr->refresh();
        $this->assertSame(IpcrStatus::Assessed, $ipcr->status);
        $this->assertNotNull($ipcr->assessed_at);
        $this->assertSame('4.000', $ipcr->core_rating);
        $this->assertSame('4.000', $ipcr->final_numerical_rating);
        $this->assertSame('Very Satisfactory', $ipcr->final_adjectival_rating);
    }

    public function test_completing_the_assessment_records_an_audit_row(): void
    {
        $ipcr = $this->submittedIpcr();
        $this->rate($ipcr);

        $this->actingAs($this->assessor->user)->post(route('ipcrs.assess', $ipcr));

        $approval = $ipcr->approvals()->first();
        $this->assertSame(ApprovalStage::Assessment, $approval->stage);
        $this->assertSame(ApprovalAction::Approved, $approval->action);
        $this->assertSame($this->assessor->id, $approval->approver_employee_id);
    }

    public function test_only_the_assessor_can_complete_the_assessment(): void
    {
        $ipcr = $this->submittedIpcr();
        $this->rate($ipcr);

        $this->actingAs($this->finalApprover->user)
            ->post(route('ipcrs.assess', $ipcr))
            ->assertForbidden();
    }

    public function test_an_ipcr_cannot_be_assessed_twice(): void
    {
        $ipcr = $this->submittedIpcr(['status' => IpcrStatus::Assessed]);
        $this->rate($ipcr);

        $this->actingAs($this->assessor->user)
            ->post(route('ipcrs.assess', $ipcr))
            ->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Final approval
    // -----------------------------------------------------------------

    public function test_the_final_approver_can_approve_an_assessed_ipcr(): void
    {
        $ipcr = $this->submittedIpcr(['status' => IpcrStatus::Assessed]);
        $this->rate($ipcr, 5, 5, 5);

        $this->actingAs($this->finalApprover->user)->post(route('ipcrs.approve', $ipcr));

        $ipcr->refresh();
        $this->assertSame(IpcrStatus::Approved, $ipcr->status);
        $this->assertNotNull($ipcr->approved_at);
        $this->assertSame('5.000', $ipcr->final_numerical_rating);
        $this->assertSame('Outstanding', $ipcr->final_adjectival_rating);
    }

    public function test_only_the_final_approver_can_approve(): void
    {
        $ipcr = $this->submittedIpcr(['status' => IpcrStatus::Assessed]);
        $this->rate($ipcr);

        $this->actingAs($this->assessor->user)
            ->post(route('ipcrs.approve', $ipcr))
            ->assertForbidden();
    }

    public function test_a_submitted_ipcr_cannot_skip_straight_to_approved(): void
    {
        $ipcr = $this->submittedIpcr();
        $this->rate($ipcr);

        $this->actingAs($this->finalApprover->user)
            ->post(route('ipcrs.approve', $ipcr))
            ->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Returning for revision
    // -----------------------------------------------------------------

    public function test_the_assessor_can_return_an_ipcr_with_remarks(): void
    {
        $ipcr = $this->submittedIpcr();

        $this->actingAs($this->assessor->user)
            ->post(route('ipcrs.return', $ipcr), ['remarks' => 'Weights do not add up.'])
            ->assertRedirect(route('approvals.inbox'));

        $this->assertSame(IpcrStatus::Returned, $ipcr->fresh()->status);

        $approval = $ipcr->approvals()->first();
        $this->assertSame(ApprovalAction::Returned, $approval->action);
        $this->assertSame('Weights do not add up.', $approval->remarks);
    }

    public function test_returning_requires_remarks(): void
    {
        $ipcr = $this->submittedIpcr();

        $this->actingAs($this->assessor->user)
            ->post(route('ipcrs.return', $ipcr), ['remarks' => ''])
            ->assertSessionHasErrors('remarks');

        $this->assertSame(IpcrStatus::Submitted, $ipcr->fresh()->status);
    }

    public function test_the_final_approver_can_also_return_an_assessed_ipcr(): void
    {
        $ipcr = $this->submittedIpcr(['status' => IpcrStatus::Assessed]);

        $this->actingAs($this->finalApprover->user)
            ->post(route('ipcrs.return', $ipcr), ['remarks' => 'Please revise the targets.']);

        $this->assertSame(IpcrStatus::Returned, $ipcr->fresh()->status);
        $this->assertSame(ApprovalStage::FinalRating, $ipcr->approvals()->first()->stage);
    }

    public function test_a_returned_ipcr_becomes_editable_by_its_owner_again(): void
    {
        $ipcr = $this->submittedIpcr();

        $this->actingAs($this->assessor->user)
            ->post(route('ipcrs.return', $ipcr), ['remarks' => 'Needs work.']);

        $this->assertTrue($ipcr->fresh()->isEditableByOwner());
    }

    public function test_a_bystander_cannot_return_someone_elses_ipcr(): void
    {
        $ipcr = $this->submittedIpcr();
        $bystander = $this->employeeWithLogin();

        $this->actingAs($bystander->user)
            ->post(route('ipcrs.return', $ipcr), ['remarks' => 'Mine now.'])
            ->assertForbidden();
    }

    // -----------------------------------------------------------------
    // What each person sees on the IPCR page
    // -----------------------------------------------------------------

    public function test_the_assessor_sees_rating_inputs_on_the_ipcr_page(): void
    {
        $ipcr = $this->submittedIpcr();
        $item = $ipcr->items->first();

        $this->actingAs($this->assessor->user)
            ->get(route('ipcrs.show', $ipcr))
            ->assertOk()
            ->assertSee('name="ratings[' . $item->id . '][quality]"', false)
            ->assertSee('Complete assessment');
    }

    public function test_the_owner_never_sees_rating_inputs_on_their_own_ipcr(): void
    {
        $ipcr = $this->submittedIpcr();
        $item = $ipcr->items->first();

        $this->actingAs($this->owner->user)
            ->get(route('ipcrs.show', $ipcr))
            ->assertOk()
            ->assertDontSee('name="ratings[' . $item->id . '][quality]"', false)
            ->assertDontSee('Complete assessment');
    }

    public function test_the_final_approver_sees_the_approve_button_not_the_rating_inputs(): void
    {
        $ipcr = $this->submittedIpcr(['status' => IpcrStatus::Assessed]);
        $item = $ipcr->items->first();
        $this->rate($ipcr);

        $this->actingAs($this->finalApprover->user)
            ->get(route('ipcrs.show', $ipcr))
            ->assertOk()
            ->assertSee('Approve final rating')
            ->assertDontSee('name="ratings[' . $item->id . '][quality]"', false);
    }

    public function test_the_sidebar_shows_a_count_of_what_is_waiting(): void
    {
        $this->submittedIpcr();

        $this->actingAs($this->assessor->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('For My Approval');

        $bystander = $this->employeeWithLogin();

        $this->actingAs($bystander->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('For My Approval');
    }

    /**
     * A head sees their inbox before anything has ever been routed to them.
     *
     * Tying the link to a routed IPCR means a newly appointed section head has
     * no way to find the inbox at all until someone happens to submit - and
     * before the first submission of a cycle, that is everybody.
     */
    public function test_a_section_head_sees_the_link_before_anything_is_submitted(): void
    {
        $section = \App\Models\Section::factory()->create();
        $head = $this->employeeWithLogin();
        $section->update(['section_head_employee_id' => $head->id]);

        $this->assertSame(0, Ipcr::query()->routedTo($head)->count(), 'Nothing has been routed yet.');

        $this->actingAs($head->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('For My Approval');
    }

    public function test_a_division_head_sees_the_link_before_anything_is_submitted(): void
    {
        $division = \App\Models\Division::factory()->create();
        $head = $this->employeeWithLogin();
        $division->update(['division_head_employee_id' => $head->id]);

        $this->actingAs($head->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('For My Approval');
    }

    public function test_the_chief_of_hospital_sees_the_link(): void
    {
        $chief = Employee::factory()->chiefOfHospital()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($chief->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('For My Approval');
    }

    public function test_rank_and_file_never_see_the_link(): void
    {
        $this->actingAs($this->employeeWithLogin()->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('For My Approval');
    }

    public function test_the_inbox_opens_and_says_it_is_empty(): void
    {
        $section = \App\Models\Section::factory()->create();
        $head = $this->employeeWithLogin();
        $section->update(['section_head_employee_id' => $head->id]);

        $this->actingAs($head->user)
            ->get(route('approvals.inbox'))
            ->assertOk()
            ->assertSee('Nothing is waiting on you');
    }

    /**
     * The link must survive an empty queue.
     *
     * Tying it to the pending count alone means it vanishes the moment an
     * approver finishes, leaving no way back to the inbox to check.
     */
    public function test_an_approver_with_an_empty_queue_still_sees_the_link(): void
    {
        $ipcr = $this->submittedIpcr(['status' => IpcrStatus::Approved]);

        $this->assertSame(0, Ipcr::query()->awaitingAssessmentBy($this->assessor)->count());

        $this->actingAs($this->assessor->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('For My Approval');
    }

    public function test_someone_who_has_never_been_an_approver_does_not_see_the_link(): void
    {
        $this->submittedIpcr();
        $bystander = $this->employeeWithLogin();

        $this->actingAs($bystander->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('For My Approval');
    }

    // -----------------------------------------------------------------
    // End to end
    // -----------------------------------------------------------------

    /**
     * The whole point of this feature: an IPCR that reaches a final rating.
     *
     * Two categories, so the derived split applies - no strategic items means
     * Core 80 / Support 20.
     */
    public function test_an_ipcr_travels_from_submitted_to_a_final_rating(): void
    {
        $ipcr = Ipcr::factory()->submitted()->create([
            'employee_id'                => $this->owner->id,
            'assessor_employee_id'       => $this->assessor->id,
            'final_approver_employee_id' => $this->finalApprover->id,
        ]);

        $core = IpcrItem::factory()->create([
            'ipcr_id' => $ipcr->id, 'category' => FunctionCategory::Core, 'weight' => 100,
        ]);
        $support = IpcrItem::factory()->create([
            'ipcr_id' => $ipcr->id, 'category' => FunctionCategory::Support, 'weight' => 100,
        ]);

        $this->actingAs($this->assessor->user)->put(route('ipcrs.ratings.update', $ipcr), [
            'ratings' => [
                $core->id    => ['quality' => 5, 'efficiency' => 5, 'timeliness' => 5],
                $support->id => ['quality' => 3, 'efficiency' => 3, 'timeliness' => 3],
            ],
        ]);

        $this->actingAs($this->assessor->user)->post(route('ipcrs.assess', $ipcr));
        $this->actingAs($this->finalApprover->user)->post(route('ipcrs.approve', $ipcr));

        $ipcr->refresh();

        $this->assertSame(IpcrStatus::Approved, $ipcr->status);
        $this->assertSame('5.000', $ipcr->core_rating);
        $this->assertSame('3.000', $ipcr->support_rating);

        // 5 * 0.80 + 3 * 0.20 = 4.6, which sits in the CSC Outstanding band (4.5 - 5.0).
        $this->assertSame('4.600', $ipcr->final_numerical_rating);
        $this->assertSame('Outstanding', $ipcr->final_adjectival_rating);
        $this->assertCount(2, $ipcr->approvals);
    }
}
