<?php

namespace Tests\Feature;

use App\Enums\IpcrStatus;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * An employee may delete their own IPCR, but only while it is a draft.
 *
 * The restriction is deliberate. `ipcrs` has no soft delete, and both
 * `ipcr_items` and `ipcr_approvals` use `cascadeOnDelete` - so a delete also
 * destroys the approval audit trail for good. Only a draft is safe: it has
 * never been passed to anyone, so no history can exist for it.
 */
class DeleteIpcrTest extends TestCase
{
    use RefreshDatabase;

    private function employeeUser(): User
    {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);

        return $user->fresh();
    }

    public function test_an_employee_can_delete_their_own_draft(): void
    {
        $user = $this->employeeUser();
        $ipcr = Ipcr::factory()->create([
            'employee_id' => $user->employee->id,
            'status'      => IpcrStatus::Draft,
        ]);

        // Back where they were, which for an employee is their own list.
        $this->actingAs($user)
            ->from(route('ipcrs.index'))
            ->delete(route('ipcrs.destroy', $ipcr))
            ->assertRedirect(route('ipcrs.index'));

        $this->assertDatabaseMissing('ipcrs', ['id' => $ipcr->id]);
    }

    public function test_deleting_a_draft_takes_its_functions_with_it(): void
    {
        $user = $this->employeeUser();
        $ipcr = Ipcr::factory()->create([
            'employee_id' => $user->employee->id,
            'status'      => IpcrStatus::Draft,
        ]);
        $item = IpcrItem::factory()->create(['ipcr_id' => $ipcr->id]);

        $this->actingAs($user)->delete(route('ipcrs.destroy', $ipcr));

        $this->assertDatabaseMissing('ipcr_items', ['id' => $item->id]);
    }

    #[DataProvider('statusesThatCannotBeDeleted')]
    public function test_an_ipcr_that_has_left_the_owners_hands_cannot_be_deleted(IpcrStatus $status): void
    {
        $user = $this->employeeUser();
        $ipcr = Ipcr::factory()->create([
            'employee_id' => $user->employee->id,
            'status'      => $status,
        ]);

        $this->actingAs($user)
            ->delete(route('ipcrs.destroy', $ipcr))
            ->assertForbidden();

        $this->assertDatabaseHas('ipcrs', ['id' => $ipcr->id]);
    }

    public static function statusesThatCannotBeDeleted(): array
    {
        return [
            'submitted' => [IpcrStatus::Submitted],
            'assessed'  => [IpcrStatus::Assessed],
            'approved'  => [IpcrStatus::Approved],
            'returned'  => [IpcrStatus::Returned],
        ];
    }

    public function test_an_employee_cannot_delete_someone_elses_draft(): void
    {
        $owner = $this->employeeUser();
        $stranger = $this->employeeUser();

        $ipcr = Ipcr::factory()->create([
            'employee_id' => $owner->employee->id,
            'status'      => IpcrStatus::Draft,
        ]);

        $this->actingAs($stranger)
            ->delete(route('ipcrs.destroy', $ipcr))
            ->assertForbidden();

        $this->assertDatabaseHas('ipcrs', ['id' => $ipcr->id]);
    }

    /**
     * Note: searching for the URL would prove nothing here. ipcrs.destroy and
     * ipcrs.show share the same URL (/ipcrs/{id}) and differ only by HTTP
     * method, so a URL match would hit the "View" link and always pass. We look
     * for the button itself.
     */
    public function test_the_list_offers_delete_on_a_draft(): void
    {
        $user = $this->employeeUser();
        Ipcr::factory()->create([
            'employee_id' => $user->employee->id,
            'status'      => IpcrStatus::Draft,
        ]);

        $this->actingAs($user)
            ->get(route('ipcrs.index'))
            ->assertOk()
            ->assertSee('>Delete</button>', false)
            ->assertSee('name="_method" value="DELETE"', false);
    }

    public function test_the_list_does_not_offer_delete_once_the_ipcr_is_submitted(): void
    {
        $user = $this->employeeUser();
        Ipcr::factory()->submitted()->create([
            'employee_id' => $user->employee->id,
        ]);

        $this->actingAs($user)
            ->get(route('ipcrs.index'))
            ->assertOk()
            ->assertDontSee('>Delete</button>', false)
            ->assertDontSee('name="_method" value="DELETE"', false);
    }
}
