<?php

namespace Tests\Feature;

use App\Enums\IpcrMode;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The employee picks targets-only or with-accomplishments straight from the
 * IPCR list - no separate page to pass through first.
 *
 * There is one way in, and it is the modal here. The old create page asked
 * nothing and quietly produced a targets-only IPCR; see RetiredSurfacesTest.
 */
class NewIpcrEntryPointTest extends TestCase
{
    use RefreshDatabase;

    private function employeeUser(): User
    {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);

        return $user->fresh();
    }

    public function test_the_list_offers_both_modes_when_a_new_ipcr_can_be_started(): void
    {
        $user = $this->employeeUser();
        IpcrPeriod::factory()->create(['status' => 'open']);

        $response = $this->actingAs($user)->get(route('ipcrs.index'));

        $response->assertOk();
        $response->assertSee('name="mode"', false);
        $response->assertSee(IpcrMode::TargetsOnly->value, false);
        $response->assertSee(IpcrMode::WithAccomplishment->value, false);
        $response->assertSee(IpcrMode::TargetsOnly->label());
        $response->assertSee(IpcrMode::WithAccomplishment->label());
    }

    public function test_the_list_explains_itself_when_no_rating_period_is_open(): void
    {
        $user = $this->employeeUser();
        IpcrPeriod::factory()->closed()->create();

        $response = $this->actingAs($user)->get(route('ipcrs.index'));

        $response->assertOk();
        $response->assertDontSee('name="mode"', false);
        $response->assertSee('No open rating period');
    }

    public function test_the_list_points_at_the_existing_ipcr_instead_of_offering_a_new_one(): void
    {
        $user = $this->employeeUser();
        $period = IpcrPeriod::factory()->create(['status' => 'open']);

        $ipcr = Ipcr::factory()->create([
            'employee_id'    => $user->employee->id,
            'ipcr_period_id' => $period->id,
        ]);

        $response = $this->actingAs($user)->get(route('ipcrs.index'));

        $response->assertOk();
        $response->assertDontSee('name="mode"', false);
        $response->assertSee('already have an IPCR');
        $response->assertSee(route('ipcrs.show', $ipcr), false);
    }

    /**
     * Every mode is offered, and each of them exactly once.
     *
     * A mode listed twice would be two radio buttons for the same choice, and
     * one missing would be a choice the employee can never make.
     */
    public function test_the_list_offers_each_mode_exactly_once(): void
    {
        $user = $this->employeeUser();
        IpcrPeriod::factory()->create(['status' => 'open']);

        $html = $this->actingAs($user)->get(route('ipcrs.index'))->getContent();
        preg_match_all('/name="mode" value="([a-z_]+)"/', $html, $matches);

        $offered = $matches[1];
        sort($offered);

        $expected = array_map(fn (IpcrMode $mode): string => $mode->value, IpcrMode::cases());
        sort($expected);

        $this->assertSame($expected, $offered);
    }
}
