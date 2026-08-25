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
 * There are still two ways to create an IPCR (this modal and the create
 * page), so a test below pins down which of them offers the choice.
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
     * Only one place chooses the mode: the modal on the list.
     *
     * The create page remains as a plain fallback, but it no longer offers the
     * choice - go through it and you get Targets only, permanently.
     */
    public function test_the_mode_choice_lives_only_in_the_list(): void
    {
        $user = $this->employeeUser();
        IpcrPeriod::factory()->create(['status' => 'open']);

        $extractModes = function (string $html): array {
            preg_match_all('/name="mode" value="([a-z_]+)"/', $html, $matches);
            $values = array_unique($matches[1]);
            sort($values);

            return $values;
        };

        $expected = array_map(fn (IpcrMode $mode): string => $mode->value, IpcrMode::cases());
        sort($expected);

        $fromList = $this->actingAs($user)->get(route('ipcrs.index'))->getContent();
        $this->assertSame($expected, $extractModes($fromList), 'The list should offer every mode.');

        $fromCreatePage = $this->actingAs($user)->get(route('ipcrs.create'))->getContent();
        $this->assertSame([], $extractModes($fromCreatePage), 'The create page should no longer ask.');
    }
}
