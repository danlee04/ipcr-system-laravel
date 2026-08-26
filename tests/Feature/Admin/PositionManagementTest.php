<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PositionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_an_admin_can_create_a_position(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.positions.store'), [
                'title' => 'Statistician II', 'item_number' => 'STAT-002', 'salary_grade' => 15,
            ])
            ->assertRedirect(route('admin.positions.index'));

        $this->assertDatabaseHas('positions', [
            'title' => 'Statistician II', 'item_number' => 'STAT-002', 'salary_grade' => 15, 'is_active' => true,
        ]);
    }

    public function test_a_position_needs_a_title(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.positions.store'), ['title' => ''])
            ->assertSessionHasErrors('title');
    }

    public function test_an_item_number_must_be_unique(): void
    {
        Position::factory()->create(['item_number' => 'STAT-002']);

        $this->actingAs($this->admin())
            ->post(route('admin.positions.store'), ['title' => 'Another', 'item_number' => 'STAT-002'])
            ->assertSessionHasErrors('item_number');
    }

    public function test_a_salary_grade_must_be_within_range(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.positions.store'), ['title' => 'Odd', 'salary_grade' => 99])
            ->assertSessionHasErrors('salary_grade');
    }

    public function test_an_admin_can_update_a_position(): void
    {
        $position = Position::factory()->create(['title' => 'Old']);

        $this->actingAs($this->admin())
            ->put(route('admin.positions.update', $position), [
                'title' => 'New', 'item_number' => $position->item_number, 'salary_grade' => 11,
            ]);

        $this->assertSame('New', $position->fresh()->title);
    }

    public function test_an_admin_can_deactivate_and_reactivate_a_position(): void
    {
        $position = Position::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())->patch(route('admin.positions.active', $position), ['active' => false]);
        $this->assertFalse($position->fresh()->is_active);

        $this->actingAs($this->admin())->patch(route('admin.positions.active', $position), ['active' => true]);
        $this->assertTrue($position->fresh()->is_active);
    }

    public function test_an_unreferenced_position_can_be_deleted(): void
    {
        $position = Position::factory()->create();

        $this->actingAs($this->admin())->delete(route('admin.positions.destroy', $position));

        $this->assertDatabaseMissing('positions', ['id' => $position->id]);
    }

    public function test_a_position_held_by_an_employee_survives_a_delete_attempt(): void
    {
        $position = Position::factory()->create();
        Employee::factory()->create(['position_id' => $position->id]);

        $this->actingAs($this->admin())->delete(route('admin.positions.destroy', $position));

        $this->assertDatabaseHas('positions', ['id' => $position->id]);
        $this->assertStringContainsString('Cannot delete', (string) session('error'));
    }

    public function test_the_page_lists_positions(): void
    {
        Position::factory()->create(['title' => 'Statistician II']);

        $this->actingAs($this->admin())
            ->get(route('admin.positions.index'))
            ->assertOk()
            ->assertSee('Statistician II');
    }

    public function test_a_non_admin_cannot_create_a_position(): void
    {
        $this->seed(RoleSeeder::class);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.positions.store'), ['title' => 'Sneaky'])
            ->assertForbidden();
    }
}
