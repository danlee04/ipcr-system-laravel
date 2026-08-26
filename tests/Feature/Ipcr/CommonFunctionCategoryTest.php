<?php

namespace Tests\Feature\Ipcr;

use App\Enums\FunctionCategory;
use App\Enums\IpcrMode;
use App\Enums\IpcrStatus;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\JobFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Common" says who may pick a function, not how it is rated.
 *
 * A common function is open to everyone, but it still belongs to core, support
 * or strategic - and that is the category the IPCR line must carry. Without
 * this, a common line lands in a bucket the rating calculator ignores while
 * the weight guard still demands it reach 100%.
 */
class CommonFunctionCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithDraft(): array
    {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);

        $ipcr = Ipcr::factory()->create([
            'employee_id' => $user->fresh()->employee->id,
            'status'      => IpcrStatus::Draft,
            'mode'        => IpcrMode::TargetsOnly,
        ]);

        return [$user->fresh(), $ipcr];
    }

    // -----------------------------------------------------------------
    // The catalog entry itself
    // -----------------------------------------------------------------

    public function test_a_normal_function_is_rated_under_its_own_category(): void
    {
        $function = JobFunction::create([
            'category' => FunctionCategory::Core, 'title' => 'Provides patient care', 'is_active' => true,
        ]);

        $this->assertSame(FunctionCategory::Core, $function->ratingCategory());
    }

    public function test_a_common_function_is_rated_under_the_category_hr_assigned(): void
    {
        $function = JobFunction::create([
            'category'        => FunctionCategory::Common,
            'rating_category' => FunctionCategory::Support,
            'title'           => 'Observes official working hours',
            'is_active'       => true,
        ]);

        $this->assertSame(FunctionCategory::Support, $function->ratingCategory());
    }

    public function test_a_common_function_with_no_category_assigned_has_none(): void
    {
        $function = JobFunction::create([
            'category' => FunctionCategory::Common, 'title' => 'Attends meetings', 'is_active' => true,
        ]);

        $this->assertNull($function->ratingCategory());
    }

    public function test_common_functions_awaiting_a_category_can_be_listed(): void
    {
        JobFunction::create(['category' => FunctionCategory::Common, 'title' => 'Unassigned', 'is_active' => true]);
        JobFunction::create([
            'category' => FunctionCategory::Common, 'rating_category' => FunctionCategory::Core,
            'title' => 'Assigned', 'is_active' => true,
        ]);
        JobFunction::create(['category' => FunctionCategory::Core, 'title' => 'A core one', 'is_active' => true]);

        $this->assertSame(['Unassigned'], JobFunction::needingRatingCategory()->pluck('title')->all());
    }

    // -----------------------------------------------------------------
    // Adding one to an IPCR
    // -----------------------------------------------------------------

    public function test_adding_a_common_function_files_it_under_its_assigned_category(): void
    {
        [$user, $ipcr] = $this->ownerWithDraft();

        $function = JobFunction::create([
            'category'        => FunctionCategory::Common,
            'rating_category' => FunctionCategory::Support,
            'title'           => 'Observes official working hours',
            'is_active'       => true,
        ]);

        $this->actingAs($user)->post(route('ipcrs.items.store', $ipcr), [
            'job_function_id' => $function->id,
            'category'        => FunctionCategory::Common->value,
            'output'          => $function->title,
            'weight'          => 100,
        ]);

        $item = $ipcr->items()->first();
        $this->assertNotNull($item, 'The line should have been added.');
        $this->assertSame(FunctionCategory::Support, $item->category, 'A line must never be stored as common.');
    }

    public function test_adding_a_common_function_with_no_category_is_refused(): void
    {
        [$user, $ipcr] = $this->ownerWithDraft();

        $function = JobFunction::create([
            'category' => FunctionCategory::Common, 'title' => 'Attends meetings', 'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('ipcrs.items.store', $ipcr), [
            'job_function_id' => $function->id,
            'category'        => FunctionCategory::Common->value,
            'output'          => $function->title,
            'weight'          => 100,
        ]);

        $this->assertSame(0, $ipcr->items()->count());
        $this->assertStringContainsString('HR', (string) session('error'));
    }

    /**
     * The safety net. Even a hand-crafted form naming "common" directly, with
     * no job function behind it, must not create a line the rating ignores.
     */
    public function test_a_free_typed_line_can_never_be_stored_as_common(): void
    {
        [$user, $ipcr] = $this->ownerWithDraft();

        $this->actingAs($user)->post(route('ipcrs.items.store', $ipcr), [
            'category' => FunctionCategory::Common->value,
            'output'   => 'Something I typed myself',
            'weight'   => 100,
        ])->assertSessionHasErrors('category');

        $this->assertSame(0, $ipcr->items()->count());
    }

    /** Offering an option that always fails validation is worse than omitting it. */
    public function test_the_manual_add_form_does_not_offer_common(): void
    {
        [$user, $ipcr] = $this->ownerWithDraft();

        $response = $this->actingAs($user)->get(route('ipcrs.show', $ipcr))->assertOk();

        $response->assertSee('<option value="core">', false);
        $response->assertDontSee('<option value="common">', false);
    }

    public function test_the_three_rated_categories_are_still_accepted(): void
    {
        [$user, $ipcr] = $this->ownerWithDraft();

        foreach ([FunctionCategory::Core, FunctionCategory::Support, FunctionCategory::Strategic] as $category) {
            $this->actingAs($user)->post(route('ipcrs.items.store', $ipcr), [
                'category' => $category->value,
                'output'   => $category->value . ' line',
                'weight'   => 100,
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(3, $ipcr->items()->count());
    }
}
