<?php

namespace Tests\Feature\Admin;

use App\Enums\IpcrStatus;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Scrapping an IPCR.
 *
 * The owner may throw away a draft - it has never been passed to anyone. HR
 * and an administrator may throw away anything that is still moving, which is
 * what setting the system up needs: a half-built test record that reached a
 * Section Head used to be stuck there for good, because the owner could no
 * longer delete it and nobody else could either.
 *
 * An approved IPCR is the exception. It is the signed record, its approvals
 * cascade away with it, and there is no soft delete to recover it from. That
 * one is reopened first - a deliberate step, recorded in its own history - and
 * only then thrown away.
 */
class DeletingAnIpcrTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function ipcr(IpcrStatus $status): Ipcr
    {
        $ipcr = Ipcr::factory()->create([
            'employee_id'  => Employee::factory()->create()->id,
            'status'       => $status,
            'submitted_at' => $status === IpcrStatus::Draft ? null : now(),
        ]);

        IpcrItem::factory()->create(['ipcr_id' => $ipcr->id]);

        return $ipcr;
    }

    /** Every state an IPCR can be caught in while it is still moving. */
    public static function movingStates(): array
    {
        return [
            'draft'              => [IpcrStatus::Draft],
            'for assessment'     => [IpcrStatus::Submitted],
            'for final approval' => [IpcrStatus::Assessed],
            'returned'           => [IpcrStatus::Returned],
        ];
    }

    // -----------------------------------------------------------------
    // HR and the administrator
    // -----------------------------------------------------------------

    #[DataProvider('movingStates')]
    public function test_an_administrator_can_scrap_one_at_any_stage(IpcrStatus $status): void
    {
        $ipcr = $this->ipcr($status);

        $this->actingAs($this->userWithRole('admin'))
            ->delete(route('ipcrs.destroy', $ipcr))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('ipcrs', ['id' => $ipcr->id]);
    }

    #[DataProvider('movingStates')]
    public function test_so_can_hr(IpcrStatus $status): void
    {
        $ipcr = $this->ipcr($status);

        $this->actingAs($this->userWithRole('hr'))->delete(route('ipcrs.destroy', $ipcr));

        $this->assertDatabaseMissing('ipcrs', ['id' => $ipcr->id]);
    }

    /** Its lines go with it rather than being left pointing at nothing. */
    public function test_the_lines_go_with_it(): void
    {
        $ipcr = $this->ipcr(IpcrStatus::Submitted);

        $this->actingAs($this->userWithRole('admin'))->delete(route('ipcrs.destroy', $ipcr));

        $this->assertDatabaseCount('ipcr_items', 0);
    }

    /**
     * The signed record needs a deliberate step first.
     *
     * Reopen puts it back to the final approval stage and records that in the
     * IPCR's own history; deleting it then is a second decision rather than
     * one click away from a finished appraisal.
     */
    public function test_an_approved_one_has_to_be_reopened_first(): void
    {
        $ipcr = $this->ipcr(IpcrStatus::Approved);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->delete(route('ipcrs.destroy', $ipcr))
            ->assertForbidden();

        $this->assertDatabaseHas('ipcrs', ['id' => $ipcr->id]);

        $this->actingAs($admin)->post(route('admin.ipcrs.reopen', $ipcr), [
            'target' => 'assessment',
            'reason' => 'A test record left over from setting the system up.',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->delete(route('ipcrs.destroy', $ipcr));

        $this->assertDatabaseMissing('ipcrs', ['id' => $ipcr->id]);
    }

    // -----------------------------------------------------------------
    // Everybody else
    // -----------------------------------------------------------------

    public function test_the_owner_still_only_scraps_their_own_draft(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $draft = Ipcr::factory()->create(['employee_id' => $employee->id, 'status' => IpcrStatus::Draft]);
        $submitted = Ipcr::factory()->submitted()->create(['employee_id' => $employee->id]);

        $this->actingAs($user->fresh())->delete(route('ipcrs.destroy', $draft));
        $this->assertDatabaseMissing('ipcrs', ['id' => $draft->id]);

        $this->actingAs($user->fresh())
            ->delete(route('ipcrs.destroy', $submitted))
            ->assertForbidden();
    }

    /** Being an approver is not being an administrator. */
    public function test_an_approver_cannot_scrap_what_is_sitting_with_them(): void
    {
        $user = User::factory()->create();
        $assessor = Employee::factory()->create(['user_id' => $user->id]);

        $ipcr = Ipcr::factory()->submitted()->create([
            'employee_id'          => Employee::factory()->create()->id,
            'assessor_employee_id' => $assessor->id,
        ]);

        $this->actingAs($user->fresh())
            ->delete(route('ipcrs.destroy', $ipcr))
            ->assertForbidden();

        $this->assertDatabaseHas('ipcrs', ['id' => $ipcr->id]);
    }

    // -----------------------------------------------------------------
    // Where the button is
    // -----------------------------------------------------------------

    /**
     * The URL alone proves nothing: GET /ipcrs/{id} is the Open link and
     * DELETE /ipcrs/{id} is this, and the two read the same. The spoofed
     * method is what says a delete form is on the page.
     */
    public function test_the_admin_list_offers_it(): void
    {
        $this->ipcr(IpcrStatus::Submitted);

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.ipcrs.index'))
            ->assertOk()
            ->assertSee('value="DELETE"', false);
    }

    public function test_the_admin_list_does_not_offer_it_on_an_approved_one(): void
    {
        $this->ipcr(IpcrStatus::Approved);

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.ipcrs.index'))
            ->assertOk()
            ->assertDontSee('value="DELETE"', false);
    }

    /** Deleting from the admin list comes back to the admin list. */
    public function test_it_returns_to_the_list_it_was_deleted_from(): void
    {
        $ipcr = $this->ipcr(IpcrStatus::Submitted);

        $this->actingAs($this->userWithRole('admin'))
            ->from(route('admin.ipcrs.index'))
            ->delete(route('ipcrs.destroy', $ipcr))
            ->assertRedirect(route('admin.ipcrs.index'));
    }
}
