<?php

namespace Tests\Feature\Admin;

use App\Models\Division;
use App\Models\Employee;
use App\Models\IpcrPeriod;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Filtering without reloading the page.
 *
 * The same action serves both readings. A browser asks for the whole page; a
 * live filter sends one header and gets back the rows alone, ready to be
 * dropped in. What matters is that the rules do not fork: searching, filtering
 * and paging are decided in exactly one place, so the rows a live filter shows
 * are the rows the URL would have shown.
 */
class LiveFilteringTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        // The summary has nothing to filter without one, and shows the
        // "no rating period" notice instead of its form.
        IpcrPeriod::factory()->create(['status' => 'open']);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /** Every list that filters. */
    public static function lists(): array
    {
        return [
            'employees' => ['admin.employees.index'],
            'positions' => ['admin.positions.index'],
            'divisions' => ['admin.divisions.index'],
            'functions' => ['admin.functions.index'],
            'ipcrs'     => ['admin.ipcrs.index'],
            'summary'   => ['admin.summary.index'],
        ];
    }

    #[DataProvider('lists')]
    public function test_the_page_is_wired_for_live_filtering(string $route): void
    {
        $html = $this->actingAs($this->admin())->get(route($route))->assertOk()->getContent();

        $this->assertStringContainsString('data-live-form', $html, 'The filter form has to be findable.');
        $this->assertStringContainsString('data-live-results', $html, 'So does the part that gets replaced.');
        $this->assertStringContainsString("liveList('" . route($route) . "')", $html);
    }

    #[DataProvider('lists')]
    public function test_the_header_asks_for_the_rows_alone(string $route): void
    {
        $html = $this->actingAs($this->admin())
            ->withHeaders(['X-Live-List' => '1'])
            ->get(route($route))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<html', $html, 'A fragment, not a page.');
        $this->assertStringNotContainsString('app-sidebar', $html);
    }

    #[DataProvider('lists')]
    public function test_without_the_header_it_is_still_a_whole_page(string $route): void
    {
        $this->actingAs($this->admin())
            ->get(route($route))
            ->assertOk()
            ->assertSee('app-sidebar', false);
    }

    // -----------------------------------------------------------------
    // The rows are the same rows
    // -----------------------------------------------------------------

    public function test_the_fragment_obeys_the_search(): void
    {
        Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        Employee::factory()->create(['first_name' => 'Jose', 'last_name' => 'Rizal']);

        $html = $this->actingAs($this->admin())
            ->withHeaders(['X-Live-List' => '1'])
            ->get(route('admin.employees.index', ['search' => 'Santos']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Maria', $html);
        $this->assertStringNotContainsString('Rizal', $html);
    }

    public function test_the_fragment_obeys_a_filter(): void
    {
        $division = Division::factory()->create();
        $section = Section::factory()->create(['division_id' => $division->id]);

        Employee::factory()->create([
            'first_name' => 'Ana', 'last_name' => 'Cruz',
            'division_id' => $division->id, 'section_id' => $section->id,
        ]);
        Employee::factory()->create(['first_name' => 'Ben', 'last_name' => 'Reyes']);

        $html = $this->actingAs($this->admin())
            ->withHeaders(['X-Live-List' => '1'])
            ->get(route('admin.employees.index', ['division' => $division->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Ana', $html);
        $this->assertStringNotContainsString('Ben', $html);
    }

    /**
     * Paging happens inside the replaced rows, so the links have to come back
     * with them - otherwise page two is reachable once and never again.
     */
    public function test_the_fragment_carries_its_own_paging(): void
    {
        Position::factory()->count(25)->create();

        $html = $this->actingAs($this->admin())
            ->withHeaders(['X-Live-List' => '1'])
            ->get(route('admin.positions.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('page=2', $html);
    }

    /** The list of a page is the same list whichever way it was asked for. */
    public function test_the_fragment_is_a_slice_of_the_page(): void
    {
        Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);

        $page = $this->actingAs($this->admin())
            ->get(route('admin.employees.index'))->getContent();

        $rows = $this->actingAs($this->admin())
            ->withHeaders(['X-Live-List' => '1'])
            ->get(route('admin.employees.index'))->getContent();

        $this->assertStringContainsString(trim($rows), $page);
    }

    // -----------------------------------------------------------------
    // Still works without any of it
    // -----------------------------------------------------------------

    /**
     * The form is an ordinary GET form. With no JavaScript the Search button
     * submits it and the page reloads filtered, exactly as before.
     */
    public function test_the_form_still_submits_on_its_own(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('method="GET"', $html);
        $this->assertStringContainsString('action="' . route('admin.employees.index') . '"', $html);
    }
}
