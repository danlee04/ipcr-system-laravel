<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every run of links in the sidebar says what it is.
 *
 * Administration already had a heading and the rest did not, so the links
 * above it read as one undifferentiated list - and the two that are there
 * conditionally, the approver's inbox and the whole admin block, appeared
 * without anything saying why this person can see them. The heading is the
 * answer to "why is this on my screen".
 */
class SidebarGroupsTest extends TestCase
{
    use RefreshDatabase;

    private function sidebar(User $user): string
    {
        $html = $this->actingAs($user->fresh())->get('/dashboard')->assertOk()->getContent();

        $start = strpos($html, 'id="app-sidebar"');
        $end = strpos($html, '</aside>');

        $this->assertNotFalse($start, 'No sidebar.');

        return substr($html, $start, $end - $start);
    }

    /** The group headings, in the order they appear. */
    private function headings(User $user): array
    {
        preg_match_all(
            '#<p[^>]*data-nav-group[^>]*>\s*([^<]+?)\s*</p>#s',
            $this->sidebar($user),
            $matches,
        );

        return $matches[1];
    }

    private function employee(): User
    {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    public function test_an_ordinary_employee_sees_one_group(): void
    {
        $this->assertSame(['My Work'], $this->headings($this->employee()));
    }

    /** The heading is why the inbox is there: they hold an approving post. */
    public function test_an_approver_gets_a_heading_over_their_inbox(): void
    {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id, 'is_chief_of_hospital' => true]);

        $this->assertSame(['My Work', 'Approvals'], $this->headings($user));
    }

    public function test_an_administrator_gets_all_three(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id, 'is_chief_of_hospital' => true]);
        $user->assignRole('admin');

        $this->assertSame(
            ['My Work', 'Approvals', 'Administration'],
            $this->headings($user),
        );
    }

    /**
     * Collapsed there is no room for the words, so the break carries the
     * grouping instead. Without it the admin links sit flush against the
     * employee's own and the sidebar reads as one long strip of icons.
     */
    public function test_a_collapsed_sidebar_still_shows_where_a_group_starts(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);
        $user->assignRole('admin');

        $sidebar = $this->sidebar($user);

        // One rule per heading, shown only where the labels are gone.
        $this->assertSame(
            substr_count($sidebar, 'data-nav-group'),
            substr_count($sidebar, 'data-nav-rule'),
        );

        $this->assertStringContainsString('data-nav-rule', $sidebar);
    }
}
