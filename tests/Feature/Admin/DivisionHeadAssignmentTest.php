<?php

namespace Tests\Feature\Admin;

use App\Models\Division;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Assigning a division head is the reason this screen exists.
 *
 * IpcrRoutingService reads divisions.division_head_employee_id to resolve an
 * approval chain. Until it is set, nobody in the division can submit an IPCR,
 * and before this screen there was no supported way to set it at all.
 */
class DivisionHeadAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_an_admin_can_assign_a_division_head(): void
    {
        $division = Division::factory()->create();
        $head = Employee::factory()->create();

        $this->actingAs($this->admin())->put(route('admin.divisions.update', $division), [
            'name' => $division->name,
            'code' => $division->code,
            'division_head_employee_id' => $head->id,
        ])->assertRedirect(route('admin.divisions.index'));

        $this->assertSame($head->id, $division->fresh()->division_head_employee_id);
    }

    public function test_an_admin_can_assign_a_head_while_creating_a_division(): void
    {
        $head = Employee::factory()->create();

        $this->actingAs($this->admin())->post(route('admin.divisions.store'), [
            'name' => 'Medical Services Division',
            'division_head_employee_id' => $head->id,
        ]);

        $this->assertSame($head->id, Division::sole()->division_head_employee_id);
    }

    public function test_an_admin_can_clear_the_head(): void
    {
        $head = Employee::factory()->create();
        $division = Division::factory()->create(['division_head_employee_id' => $head->id]);

        $this->actingAs($this->admin())->put(route('admin.divisions.update', $division), [
            'name' => $division->name,
            'code' => $division->code,
            'division_head_employee_id' => null,
        ]);

        $this->assertNull($division->fresh()->division_head_employee_id);
    }

    public function test_a_head_must_be_an_existing_employee(): void
    {
        $division = Division::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.divisions.update', $division), [
                'name' => $division->name,
                'code' => $division->code,
                'division_head_employee_id' => 9999,
            ])
            ->assertSessionHasErrors('division_head_employee_id');
    }

    public function test_the_page_offers_a_head_select_listing_active_employees(): void
    {
        Division::factory()->create();
        $active = Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        Employee::factory()->create(['first_name' => 'Retired', 'last_name' => 'Person', 'is_active' => false]);

        $this->actingAs($this->admin())
            ->get(route('admin.divisions.index'))
            ->assertOk()
            ->assertSee('name="division_head_employee_id"', false)
            ->assertSee($active->full_name)
            ->assertDontSee('Retired Person');
    }

    public function test_the_page_shows_which_divisions_still_need_a_head(): void
    {
        Division::factory()->create(['name' => 'Headless Division']);

        $this->actingAs($this->admin())
            ->get(route('admin.divisions.index'))
            ->assertOk()
            ->assertSee('Not assigned');
    }

    public function test_the_page_warns_when_there_are_no_employees_to_choose_from(): void
    {
        Division::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.divisions.index'))
            ->assertOk()
            ->assertSee('no employees yet');
    }

    public function test_an_admin_can_edit_a_division_from_the_list(): void
    {
        $division = Division::factory()->create(['name' => 'Before']);

        $this->actingAs($this->admin())
            ->get(route('admin.divisions.index'))
            ->assertOk()
            ->assertSee('>Edit</button>', false)
            ->assertSee(route('admin.divisions.update', $division), false);
    }
}
