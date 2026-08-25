<?php

namespace Tests\Feature\Admin;

use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;
use App\Services\IpcrRoutingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_an_admin_can_create_a_section_inside_a_division(): void
    {
        $division = Division::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.sections.store'), [
                'division_id' => $division->id,
                'name'        => 'Nursing Section',
                'code'        => 'NUR',
            ])
            ->assertRedirect(route('admin.divisions.index'));

        $this->assertDatabaseHas('sections', [
            'division_id' => $division->id, 'name' => 'Nursing Section', 'is_active' => true,
        ]);
    }

    public function test_a_section_must_belong_to_an_existing_division(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.sections.store'), ['division_id' => 9999, 'name' => 'Orphan'])
            ->assertSessionHasErrors('division_id');

        $this->assertSame(0, Section::count());
    }

    public function test_a_section_needs_a_name(): void
    {
        $division = Division::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.sections.store'), ['division_id' => $division->id, 'name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_a_section_code_must_be_unique(): void
    {
        $division = Division::factory()->create();
        Section::factory()->create(['code' => 'NUR']);

        $this->actingAs($this->admin())
            ->post(route('admin.sections.store'), [
                'division_id' => $division->id, 'name' => 'Another', 'code' => 'NUR',
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_an_admin_can_move_a_section_to_another_division(): void
    {
        $section = Section::factory()->create();
        $target = Division::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.sections.update', $section), [
                'division_id' => $target->id, 'name' => $section->name, 'code' => $section->code,
            ])
            ->assertRedirect(route('admin.divisions.index'));

        $this->assertSame($target->id, $section->fresh()->division_id);
    }

    public function test_an_admin_can_deactivate_and_reactivate_a_section(): void
    {
        $section = Section::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())->patch(route('admin.sections.active', $section), ['active' => false]);
        $this->assertFalse($section->fresh()->is_active);

        $this->actingAs($this->admin())->patch(route('admin.sections.active', $section), ['active' => true]);
        $this->assertTrue($section->fresh()->is_active);
    }

    public function test_an_unreferenced_section_can_be_deleted(): void
    {
        $section = Section::factory()->create();

        $this->actingAs($this->admin())->delete(route('admin.sections.destroy', $section));

        $this->assertDatabaseMissing('sections', ['id' => $section->id]);
    }

    public function test_a_section_with_employees_survives_a_delete_attempt(): void
    {
        $section = Section::factory()->create();
        Employee::factory()->create(['section_id' => $section->id]);

        $this->actingAs($this->admin())->delete(route('admin.sections.destroy', $section));

        $this->assertDatabaseHas('sections', ['id' => $section->id]);
        $this->assertStringContainsString('Cannot delete', (string) session('error'));
    }

    public function test_the_page_shows_sections_under_their_division(): void
    {
        $division = Division::factory()->create(['name' => 'Medical Services Division']);
        Section::factory()->create(['division_id' => $division->id, 'name' => 'Nursing Section']);

        $this->actingAs($this->admin())
            ->get(route('admin.divisions.index'))
            ->assertOk()
            ->assertSeeInOrder(['Medical Services Division', 'Nursing Section']);
    }

    public function test_an_admin_can_assign_a_section_head(): void
    {
        $section = Section::factory()->create();
        $head = Employee::factory()->create();

        $this->actingAs($this->admin())->put(route('admin.sections.update', $section), [
            'division_id' => $section->division_id,
            'name'        => $section->name,
            'code'        => $section->code,
            'section_head_employee_id' => $head->id,
        ]);

        $this->assertSame($head->id, $section->fresh()->section_head_employee_id);
    }

    public function test_a_section_head_must_be_an_existing_employee(): void
    {
        $section = Section::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.sections.update', $section), [
                'division_id' => $section->division_id,
                'name'        => $section->name,
                'section_head_employee_id' => 9999,
            ])
            ->assertSessionHasErrors('section_head_employee_id');
    }

    /**
     * The payoff for the whole admin area.
     *
     * With both heads assigned through these screens, an employee in that
     * section can finally have an approval chain resolved. Before this,
     * IpcrRoutingService threw and IPCR submission was impossible for everyone.
     */
    public function test_assigning_both_heads_unblocks_ipcr_routing(): void
    {
        $division = Division::factory()->create();
        $section = Section::factory()->create(['division_id' => $division->id]);

        $sectionHead = Employee::factory()->create(['section_id' => $section->id]);
        $divisionHead = Employee::factory()->create(['division_id' => $division->id]);
        $rankAndFile = Employee::factory()->create([
            'section_id' => $section->id, 'division_id' => $division->id,
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.sections.update', $section), [
            'division_id' => $division->id,
            'name'        => $section->name,
            'code'        => $section->code,
            'section_head_employee_id' => $sectionHead->id,
        ]);

        $this->actingAs($admin)->put(route('admin.divisions.update', $division), [
            'name' => $division->name,
            'code' => $division->code,
            'division_head_employee_id' => $divisionHead->id,
        ]);

        $chain = app(IpcrRoutingService::class)->resolve($rankAndFile->fresh());

        $this->assertSame($sectionHead->id, $chain->assessor->id);
        $this->assertSame($divisionHead->id, $chain->finalApprover->id);
    }

    public function test_a_non_admin_cannot_create_a_section(): void
    {
        $this->seed(RoleSeeder::class);
        $division = Division::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.sections.store'), ['division_id' => $division->id, 'name' => 'Sneaky'])
            ->assertForbidden();
    }
}
