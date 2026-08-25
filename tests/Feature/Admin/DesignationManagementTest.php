<?php

namespace Tests\Feature\Admin;

use App\Models\Designation;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignationManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_an_admin_can_create_a_designation(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.designations.store'), ['title' => 'OIC - Budget Officer'])
            ->assertRedirect(route('admin.job-titles.index', ['tab' => 'designations']));

        $this->assertDatabaseHas('designations', ['title' => 'OIC - Budget Officer', 'is_active' => true]);
    }

    public function test_a_designation_needs_a_title(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.designations.store'), ['title' => ''])
            ->assertSessionHasErrors('title');

        $this->assertSame(0, Designation::count());
    }

    public function test_an_admin_can_update_a_designation(): void
    {
        $designation = Designation::factory()->create(['title' => 'Old']);

        $this->actingAs($this->admin())
            ->put(route('admin.designations.update', $designation), ['title' => 'New']);

        $this->assertSame('New', $designation->fresh()->title);
    }

    public function test_an_admin_can_deactivate_and_reactivate_a_designation(): void
    {
        $designation = Designation::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())->patch(route('admin.designations.active', $designation), ['active' => false]);
        $this->assertFalse($designation->fresh()->is_active);

        $this->actingAs($this->admin())->patch(route('admin.designations.active', $designation), ['active' => true]);
        $this->assertTrue($designation->fresh()->is_active);
    }

    public function test_an_unreferenced_designation_can_be_deleted(): void
    {
        $designation = Designation::factory()->create();

        $this->actingAs($this->admin())->delete(route('admin.designations.destroy', $designation));

        $this->assertDatabaseMissing('designations', ['id' => $designation->id]);
    }

    public function test_the_designations_tab_lists_them(): void
    {
        Designation::factory()->create(['title' => 'OIC - HRMO']);

        $this->actingAs($this->admin())
            ->get(route('admin.job-titles.index', ['tab' => 'designations']))
            ->assertOk()
            ->assertSee('OIC - HRMO');
    }

    public function test_the_positions_tab_does_not_show_designations(): void
    {
        Designation::factory()->create(['title' => 'OIC - HRMO']);
        Position::factory()->create(['title' => 'Statistician II']);

        $this->actingAs($this->admin())
            ->get(route('admin.job-titles.index'))
            ->assertOk()
            ->assertSee('Statistician II')
            ->assertDontSee('OIC - HRMO');
    }

    public function test_a_non_admin_cannot_create_a_designation(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.designations.store'), ['title' => 'Sneaky'])
            ->assertForbidden();
    }
}
