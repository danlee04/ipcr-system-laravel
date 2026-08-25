<?php

namespace Tests\Feature;

use App\Enums\IpcrMode;
use App\Enums\IpcrStatus;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\IpcrPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The owner chooses whether their IPCR is "targets only" or also carries the
 * actual accomplishments. This is only a display preference - there is still a
 * single approval cycle - but it controls which fields appear and what is
 * required before submitting.
 */
class IpcrModeTest extends TestCase
{
    use RefreshDatabase;

    private function employeeUser(): User
    {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);

        return $user->fresh();
    }

    private function openPeriod(): IpcrPeriod
    {
        return IpcrPeriod::factory()->create(['status' => 'open']);
    }

    // -----------------------------------------------------------------
    // The enum itself
    // -----------------------------------------------------------------

    public function test_only_the_accomplishment_mode_shows_the_accomplishment_field(): void
    {
        $this->assertFalse(IpcrMode::TargetsOnly->showsAccomplishment());
        $this->assertTrue(IpcrMode::WithAccomplishment->showsAccomplishment());
    }

    // -----------------------------------------------------------------
    // Choosing at creation
    // -----------------------------------------------------------------

    public function test_an_employee_can_create_a_targets_only_ipcr(): void
    {
        $user = $this->employeeUser();
        $this->openPeriod();

        $this->actingAs($user)
            ->post(route('ipcrs.store'), ['mode' => IpcrMode::TargetsOnly->value])
            ->assertRedirect();

        $this->assertSame(IpcrMode::TargetsOnly, Ipcr::sole()->mode);
    }

    public function test_an_employee_can_create_an_ipcr_that_includes_accomplishments(): void
    {
        $user = $this->employeeUser();
        $this->openPeriod();

        $this->actingAs($user)
            ->post(route('ipcrs.store'), ['mode' => IpcrMode::WithAccomplishment->value])
            ->assertRedirect();

        $this->assertSame(IpcrMode::WithAccomplishment, Ipcr::sole()->mode);
    }

    /**
     * The create page no longer asks - the modal on the list is what chooses.
     * So when no mode is supplied we do not error; we hand back Targets only,
     * the safer default because it demands no accomplishment before submitting.
     * There is no way to change it afterwards - only the modal decides.
     */
    public function test_creating_without_a_mode_falls_back_to_targets_only(): void
    {
        $user = $this->employeeUser();
        $this->openPeriod();

        $this->actingAs($user)
            ->post(route('ipcrs.store'), [])
            ->assertRedirect();

        $this->assertSame(IpcrMode::TargetsOnly, Ipcr::sole()->mode);
    }

    public function test_creating_an_ipcr_rejects_an_unknown_mode(): void
    {
        $user = $this->employeeUser();
        $this->openPeriod();

        $this->actingAs($user)
            ->post(route('ipcrs.store'), ['mode' => 'whatever'])
            ->assertSessionHasErrors('mode');

        $this->assertSame(0, Ipcr::count());
    }

    // -----------------------------------------------------------------
    // Effect on the item form
    // -----------------------------------------------------------------

    public function test_an_accomplishment_is_dropped_when_the_ipcr_is_targets_only(): void
    {
        $user = $this->employeeUser();
        $ipcr = Ipcr::factory()->create([
            'employee_id' => $user->employee->id,
            'mode'        => IpcrMode::TargetsOnly,
        ]);
        $item = IpcrItem::factory()->create(['ipcr_id' => $ipcr->id]);

        $this->actingAs($user)->put(route('ipcrs.items.update', [$ipcr, $item]), [
            'output'                => 'Written report',
            'actual_accomplishment' => 'This must not be saved',
        ])->assertRedirect();

        $this->assertNull($item->fresh()->actual_accomplishment);
    }

    public function test_an_accomplishment_is_saved_when_the_ipcr_includes_them(): void
    {
        $user = $this->employeeUser();
        $ipcr = Ipcr::factory()->create([
            'employee_id' => $user->employee->id,
            'mode'        => IpcrMode::WithAccomplishment,
        ]);
        $item = IpcrItem::factory()->create(['ipcr_id' => $ipcr->id]);

        $this->actingAs($user)->put(route('ipcrs.items.update', [$ipcr, $item]), [
            'output'                => 'Written report',
            'actual_accomplishment' => '12 reports submitted',
        ])->assertRedirect();

        $this->assertSame('12 reports submitted', $item->fresh()->actual_accomplishment);
    }

    // -----------------------------------------------------------------
    // What appears on screen
    // -----------------------------------------------------------------

    public function test_the_accomplishment_box_is_hidden_on_a_targets_only_ipcr(): void
    {
        $user = $this->employeeUser();
        $ipcr = Ipcr::factory()->create([
            'employee_id' => $user->employee->id,
            'mode'        => IpcrMode::TargetsOnly,
        ]);
        IpcrItem::factory()->create(['ipcr_id' => $ipcr->id]);

        $this->actingAs($user)
            ->get(route('ipcrs.show', $ipcr))
            ->assertOk()
            ->assertDontSee('name="actual_accomplishment"', false);
    }

    public function test_the_accomplishment_box_is_shown_when_the_ipcr_includes_them(): void
    {
        $user = $this->employeeUser();
        $ipcr = Ipcr::factory()->create([
            'employee_id' => $user->employee->id,
            'mode'        => IpcrMode::WithAccomplishment,
        ]);
        IpcrItem::factory()->create(['ipcr_id' => $ipcr->id]);

        $this->actingAs($user)
            ->get(route('ipcrs.show', $ipcr))
            ->assertOk()
            ->assertSee('name="actual_accomplishment"', false);
    }

    // -----------------------------------------------------------------
    // Submit guard
    // -----------------------------------------------------------------

    public function test_submitting_is_blocked_while_an_item_has_no_accomplishment(): void
    {
        $user = $this->employeeUser();
        $ipcr = Ipcr::factory()->create([
            'employee_id' => $user->employee->id,
            'mode'        => IpcrMode::WithAccomplishment,
        ]);
        IpcrItem::factory()->accomplished()->create(['ipcr_id' => $ipcr->id]);
        IpcrItem::factory()->create(['ipcr_id' => $ipcr->id]);

        $this->actingAs($user)->post(route('ipcrs.submit', $ipcr));

        $this->assertStringContainsString('accomplishment', (string) session('error'));
        $this->assertSame(IpcrStatus::Draft, $ipcr->fresh()->status);
    }

    public function test_a_targets_only_ipcr_is_never_blocked_for_missing_accomplishments(): void
    {
        $user = $this->employeeUser();
        $ipcr = Ipcr::factory()->create([
            'employee_id' => $user->employee->id,
            'mode'        => IpcrMode::TargetsOnly,
        ]);
        IpcrItem::factory()->create(['ipcr_id' => $ipcr->id]);

        $this->actingAs($user)->post(route('ipcrs.submit', $ipcr));

        // This fixture has no section/division head set, so routing still fails.
        // What matters is that the accomplishment guard is NOT what blocked it -
        // meaning we got past that check.
        $this->assertStringNotContainsString('accomplishment', (string) session('error'));
    }
}
