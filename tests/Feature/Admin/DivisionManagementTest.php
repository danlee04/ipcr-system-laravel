<?php

namespace Tests\Feature\Admin;

use App\Models\Division;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DivisionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_an_admin_can_create_a_division(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.divisions.store'), ['name' => 'Medical Division', 'code' => 'MED'])
            ->assertRedirect(route('admin.divisions.index'));

        $this->assertDatabaseHas('divisions', [
            'name' => 'Medical Division', 'code' => 'MED', 'is_active' => true,
        ]);
    }

    public function test_a_division_needs_a_name(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.divisions.store'), ['name' => '', 'code' => 'MED'])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Division::count());
    }

    public function test_a_division_code_must_be_unique(): void
    {
        Division::factory()->create(['code' => 'MED']);

        $this->actingAs($this->admin())
            ->post(route('admin.divisions.store'), ['name' => 'Another', 'code' => 'MED'])
            ->assertSessionHasErrors('code');
    }

    public function test_a_division_code_may_be_left_blank(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.divisions.store'), ['name' => 'No Code Division', 'code' => null])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('divisions', ['name' => 'No Code Division', 'code' => null]);
    }

    public function test_an_admin_can_rename_a_division(): void
    {
        $division = Division::factory()->create(['name' => 'Old']);

        $this->actingAs($this->admin())
            ->put(route('admin.divisions.update', $division), ['name' => 'New', 'code' => $division->code])
            ->assertRedirect(route('admin.divisions.index'));

        $this->assertSame('New', $division->fresh()->name);
    }

    public function test_updating_a_division_ignores_its_own_code_when_checking_uniqueness(): void
    {
        $division = Division::factory()->create(['code' => 'MED']);

        $this->actingAs($this->admin())
            ->put(route('admin.divisions.update', $division), ['name' => 'Renamed', 'code' => 'MED'])
            ->assertSessionHasNoErrors();
    }

    public function test_an_admin_can_deactivate_and_reactivate_a_division(): void
    {
        $division = Division::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())
            ->patch(route('admin.divisions.active', $division), ['active' => false]);
        $this->assertFalse($division->fresh()->is_active);

        $this->actingAs($this->admin())
            ->patch(route('admin.divisions.active', $division), ['active' => true]);
        $this->assertTrue($division->fresh()->is_active);
    }

    public function test_an_unreferenced_division_can_be_deleted(): void
    {
        $division = Division::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.divisions.destroy', $division))
            ->assertRedirect(route('admin.divisions.index'));

        $this->assertDatabaseMissing('divisions', ['id' => $division->id]);
    }

    public function test_a_referenced_division_survives_a_delete_attempt(): void
    {
        $division = Division::factory()->create();
        Section::factory()->create(['division_id' => $division->id]);

        $this->actingAs($this->admin())->delete(route('admin.divisions.destroy', $division));

        $this->assertDatabaseHas('divisions', ['id' => $division->id]);
        $this->assertStringContainsString('Cannot delete', (string) session('error'));
    }

    public function test_the_divisions_page_lists_them(): void
    {
        Division::factory()->create(['name' => 'Medical Division']);

        $this->actingAs($this->admin())
            ->get(route('admin.divisions.index'))
            ->assertOk()
            ->assertSee('Medical Division');
    }

    public function test_a_non_admin_cannot_create_a_division(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.divisions.store'), ['name' => 'Sneaky'])
            ->assertForbidden();

        $this->assertSame(0, Division::count());
    }
}
