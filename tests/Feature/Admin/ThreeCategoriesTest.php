<?php

namespace Tests\Feature\Admin;

use App\Enums\FunctionCategory;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\JobFunction;
use App\Models\Position;
use App\Models\User;
use App\Services\FunctionCatalogService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Three kinds of function, and nothing else.
 *
 * "Common" was never a kind of work - it said who a function reached, and it
 * sat in the box that says what the work is. That cost a second question,
 * "counts towards", which had to be answered for the rating to know anything,
 * and which could be forgotten. A function open to everyone is now simply a
 * Core, Support or Strategic function that names neither a position nor a
 * designation.
 */
class ThreeCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category'   => FunctionCategory::Support->value,
            'title'      => 'Attends the flag ceremony',
            'applies_to' => 'everyone',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // The three
    // -----------------------------------------------------------------

    /**
     * Three, and in reading order. cases() is what fills every dropdown and
     * every list, so the order it is declared in is the order the hospital
     * sees - Core first, Strategic last, the same as the sheet.
     */
    public function test_there_are_exactly_three_categories_in_reading_order(): void
    {
        $this->assertSame(
            ['core', 'support', 'strategic'],
            array_column(FunctionCategory::cases(), 'value')
        );
    }

    /**
     * The form opens on the category the function actually is.
     *
     * It used to open on Strategic whatever it was. The select carried an
     * x-model pointing at nothing - the Alpine scope behind it had been taken
     * away when "applies to" became its own question - so Alpine emptied the
     * select on load and the browser fell back to the first option. Editing a
     * Core function and pressing Save turned it Strategic.
     */
    public function test_the_form_opens_on_the_category_the_function_is(): void
    {
        $position = \App\Models\Position::factory()->create();

        $core = \App\Models\JobFunction::create([
            'category' => FunctionCategory::Core, 'position_id' => $position->id,
            'title' => 'Provides direct patient care', 'is_active' => true,
        ]);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('x-model="category"', $html, 'Nothing may overwrite the chosen category.');
        $this->assertStringContainsString('value="core" selected', $html);

        $this->assertGreaterThan(0, $core->id);
    }

    /** And a new one opens on Core, which is what most functions are. */
    public function test_a_new_function_opens_on_core(): void
    {
        \App\Models\Position::factory()->create();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->getContent();

        // The create modal comes last on the page; its own select is the one
        // with nothing saved behind it.
        $create = substr($html, strrpos($html, 'name="category"'));

        $this->assertStringContainsString('value="core" selected', $create);
        $this->assertStringNotContainsString('value="strategic" selected', $create);
    }

    public function test_each_one_is_called_a_function(): void
    {
        $this->assertSame('Strategic Function', FunctionCategory::Strategic->label());
        $this->assertSame('Core Function', FunctionCategory::Core->label());
        $this->assertSame('Support Function', FunctionCategory::Support->label());
    }

    public function test_common_is_gone(): void
    {
        $this->assertNull(FunctionCategory::tryFrom('common'));
    }

    // -----------------------------------------------------------------
    // Reaching everyone is now a scope
    // -----------------------------------------------------------------

    public function test_a_function_can_be_saved_for_everyone(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $function = JobFunction::first();

        $this->assertSame(FunctionCategory::Support, $function->category);
        $this->assertNull($function->position_id);
        $this->assertNull($function->designation_id);
    }

    /** It keeps its real category, so nothing needs to say what it counts as. */
    public function test_it_reaches_everybody_in_its_own_category(): void
    {
        $this->actingAs($this->admin())->post(route('admin.functions.store'), $this->payload());

        $employee = Employee::factory()->create(['position_id' => Position::factory()->create()->id]);
        $catalog = app(FunctionCatalogService::class)->availableFor($employee);

        $this->assertCount(1, $catalog->support);
        $this->assertSame('Attends the flag ceremony', $catalog->support->first()->title);
        $this->assertCount(0, $catalog->core);
    }

    public function test_an_employee_with_no_position_at_all_still_gets_it(): void
    {
        $this->actingAs($this->admin())->post(route('admin.functions.store'), $this->payload());

        $catalog = app(FunctionCatalogService::class)->availableFor(
            Employee::factory()->create(['position_id' => null])
        );

        $this->assertCount(1, $catalog->support);
    }

    /** Choosing "everyone" discards any link the hidden branches carried. */
    public function test_everyone_takes_no_position_or_designation(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload([
                'position_id'    => Position::factory()->create()->id,
                'designation_id' => Designation::factory()->create()->id,
            ]))
            ->assertSessionHasNoErrors();

        $function = JobFunction::first();

        $this->assertNull($function->position_id);
        $this->assertNull($function->designation_id);
    }

    // -----------------------------------------------------------------
    // The other two routes still insist on a link
    // -----------------------------------------------------------------

    public function test_a_position_function_still_needs_a_position(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload(['applies_to' => 'position']))
            ->assertSessionHasErrors('position_id');
    }

    public function test_a_designation_function_still_needs_a_designation(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.functions.store'), $this->payload(['applies_to' => 'designation']))
            ->assertSessionHasErrors('designation_id');
    }

    // -----------------------------------------------------------------
    // Nothing is left over
    // -----------------------------------------------------------------

    /**
     * The warning existed because a common function with no "counts towards"
     * could not be added to an IPCR. There is no such state any more.
     */
    public function test_the_page_no_longer_warns_about_unfiled_functions(): void
    {
        $this->actingAs($this->admin())->post(route('admin.functions.store'), $this->payload());

        $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->assertDontSee('cannot be added to an IPCR')
            ->assertDontSee('Counts towards');
    }

    public function test_the_form_offers_all_three_routes(): void
    {
        Position::factory()->create();
        Designation::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->assertSee('name="applies_to" value="everyone"', false)
            ->assertSee('name="applies_to" value="position"', false)
            ->assertSee('name="applies_to" value="designation"', false);
    }

    public function test_the_list_says_a_function_reaches_everyone(): void
    {
        $this->actingAs($this->admin())->post(route('admin.functions.store'), $this->payload());

        $this->actingAs($this->admin())
            ->get(route('admin.functions.index'))
            ->assertOk()
            ->assertSee('Everyone');
    }
}
