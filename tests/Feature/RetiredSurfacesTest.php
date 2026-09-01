<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Things the system used to have and no longer does.
 *
 * A column nothing reads is not harmless. It shows up in every dump and every
 * schema anyone consults, and the next person to read it reasonably assumes it
 * means something - so it gets filled in, or worse, relied on. The same goes
 * for a page with no link leading to it.
 */
class RetiredSurfacesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Each of these outlived whatever wanted it.
     *
     * `common_weight` and `common_rating` belonged to a fourth category that
     * no longer exists. `default_weight` was a suggested weight, replaced by
     * filling in whatever the category has not spent. `date_hired` was asked
     * for on a form that stopped asking.
     */
    public static function droppedColumns(): array
    {
        return [
            'ipcrs.common_weight'            => ['ipcrs', 'common_weight'],
            'ipcrs.common_rating'            => ['ipcrs', 'common_rating'],
            'job_functions.default_weight'   => ['job_functions', 'default_weight'],
            'employees.date_hired'           => ['employees', 'date_hired'],
        ];
    }

    #[DataProvider('droppedColumns')]
    public function test_the_column_is_gone(string $table, string $column): void
    {
        $this->assertFalse(
            Schema::hasColumn($table, $column),
            "{$table}.{$column} is still there, and nothing reads it."
        );
    }

    /** The columns beside them are untouched - this was a removal, not a rewrite. */
    public function test_the_columns_that_earn_their_place_remain(): void
    {
        foreach (['strategic_weight', 'core_weight', 'support_weight', 'final_numerical_rating'] as $column) {
            $this->assertTrue(Schema::hasColumn('ipcrs', $column));
        }
    }

    /**
     * Typing the address gets you to work, not to a signpost.
     *
     * The root used to answer with Laravel's starter page - a screen about the
     * framework, with links to its documentation, in front of a hospital's
     * appraisal system. There is nothing to say at that address that the
     * login form does not say better.
     */
    public function test_the_root_sends_a_guest_to_the_login_form(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    /**
     * Wherever that account belongs, which is not the same place for
     * everybody: a head gets the dashboard, an employee their own sheets, and
     * a login HR has not finished setting up gets its profile.
     */
    public function test_the_root_sends_a_signed_in_user_where_they_belong(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)->get('/')->assertRedirect(route('profile.edit'));
    }

    /** And the page it used to render is gone with it. */
    public function test_the_starter_page_is_gone(): void
    {
        $this->assertFalse(
            view()->exists('welcome'),
            'The Laravel starter page is still in the views.',
        );
    }

    /**
     * The IPCR is started from the list, in a modal that asks for the mode.
     * The old page asked for nothing and quietly produced a targets-only IPCR;
     * nothing linked to it, so the only way to reach it was to type the URL.
     */
    public function test_the_old_create_page_is_gone(): void
    {
        $this->assertFalse(
            app('router')->has('ipcrs.create'),
            'The create page has no link leading to it and no question to ask.'
        );
    }
}
