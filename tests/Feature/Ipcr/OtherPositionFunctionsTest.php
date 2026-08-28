<?php

namespace Tests\Feature\Ipcr;

use App\Enums\FunctionCategory;
use App\Enums\IpcrStatus;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrPeriod;
use App\Models\JobFunction;
use App\Models\Position;
use App\Models\User;
use App\Services\FunctionCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Functions written down against somebody else's post.
 *
 * People do work the catalog files elsewhere: covering a vacancy, a task that
 * moved before the catalog caught up, a job shared between two posts. Those
 * lines used to be unreachable - not offered, and refused if the id was sent
 * anyway - which left the employee with no way to report work they had
 * actually done.
 *
 * They are offered now, but in a place of their own, shut until it is opened.
 * Borrowing another post's function should be a deliberate act, not something
 * ticked by accident in the middle of your own list.
 *
 * Two doors stay closed. A designation is a role somebody was appointed to,
 * so its functions belong to whoever holds it and to nobody else. And a
 * retired function is retired for everyone.
 */
class OtherPositionFunctionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Ipcr $ipcr;

    private Position $mine;

    private Position $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mine = Position::factory()->create(['title' => 'Nurse II']);
        $this->theirs = Position::factory()->create(['title' => 'Administrative Officer IV']);

        $user = User::factory()->create();
        $employee = Employee::factory()->create([
            'user_id' => $user->id, 'position_id' => $this->mine->id,
        ]);

        $this->owner = $user->fresh();
        $this->ipcr = Ipcr::factory()->create([
            'employee_id'    => $employee->id,
            'ipcr_period_id' => IpcrPeriod::factory()->create(['status' => 'open'])->id,
            'status'         => IpcrStatus::Draft,
        ]);
    }

    private function make(string $title, array $attributes = []): JobFunction
    {
        return JobFunction::create(array_merge([
            'category'  => FunctionCategory::Core,
            'title'     => $title,
            'is_active' => true,
        ], $attributes));
    }

    private function elsewhere(): \Illuminate\Support\Collection
    {
        return app(FunctionCatalogService::class)
            ->availableFor($this->ipcr->employee->fresh())
            ->elsewhere;
    }

    private function picker(): string
    {
        return $this->actingAs($this->owner)
            ->get(route('ipcrs.show', $this->ipcr))
            ->assertOk()
            ->getContent();
    }

    // -----------------------------------------------------------------
    // What the fourth bucket holds
    // -----------------------------------------------------------------

    public function test_a_function_under_another_position_is_offered(): void
    {
        $function = $this->make('Prepares the purchase request', ['position_id' => $this->theirs->id]);

        $this->assertTrue($this->elsewhere()->contains($function));
    }

    /** It is already in the first column; twice is a longer list, not a fuller one. */
    public function test_their_own_position_is_not_repeated_there(): void
    {
        $this->make('Provides direct patient care', ['position_id' => $this->mine->id]);

        $this->assertCount(0, $this->elsewhere());
    }

    /** Already the second column, and it belongs to nobody in particular. */
    public function test_a_function_open_to_everyone_is_not_listed_there(): void
    {
        $this->make('Observes the working hours');

        $this->assertCount(0, $this->elsewhere());
    }

    /**
     * A designation is an appointment. Its functions are the work of whoever
     * currently holds it, and borrowing one would be claiming the post.
     */
    public function test_a_function_under_a_designation_stays_out(): void
    {
        $this->make('Signs the disbursement voucher', [
            'designation_id' => Designation::factory()->create()->id,
        ]);

        $this->assertCount(0, $this->elsewhere());
    }

    public function test_a_retired_function_stays_out(): void
    {
        $this->make('The old way', ['position_id' => $this->theirs->id, 'is_active' => false]);

        $this->assertCount(0, $this->elsewhere());
    }

    // -----------------------------------------------------------------
    // On the page
    // -----------------------------------------------------------------

    /** Which post it came from is the whole point of borrowing one. */
    public function test_they_are_grouped_under_the_position_they_belong_to(): void
    {
        $this->make('Prepares the purchase request', ['position_id' => $this->theirs->id]);

        $html = $this->picker();

        $this->assertStringContainsString('From another position', $html);
        $this->assertStringContainsString('Administrative Officer IV', $html);

        $heading = strpos($html, 'Administrative Officer IV');
        $this->assertLessThan(strpos($html, 'Prepares the purchase request'), $heading);
    }

    /** Nothing borrowed to offer, so nothing to open. */
    public function test_the_section_is_absent_when_no_other_post_has_anything(): void
    {
        $this->make('Provides direct patient care', ['position_id' => $this->mine->id]);

        $this->assertStringNotContainsString('From another position', $this->picker());
    }

    /**
     * The two everyday sources take half the width each. They used to be two
     * thirds against one, which left the hospital's short list stranded in a
     * narrow gutter.
     */
    public function test_the_two_everyday_sources_are_equal_columns(): void
    {
        $this->make('Provides direct patient care', ['position_id' => $this->mine->id]);
        $this->make('Observes the working hours');

        $html = $this->picker();

        $this->assertStringContainsString('lg:grid-cols-2', $html);
        $this->assertStringNotContainsString('lg:col-span-2', $html);
    }

    // -----------------------------------------------------------------
    // Adding one
    // -----------------------------------------------------------------

    public function test_one_can_be_added_to_the_ipcr(): void
    {
        $function = $this->make('Prepares the purchase request', ['position_id' => $this->theirs->id]);

        $this->actingAs($this->owner)
            ->post(route('ipcrs.items.catalog', $this->ipcr), ['job_function_ids' => [$function->id]])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ipcr_items', [
            'ipcr_id'         => $this->ipcr->id,
            'job_function_id' => $function->id,
            'output'          => 'Prepares the purchase request',
        ]);
    }

    public function test_a_designation_function_is_still_refused(): void
    {
        $function = $this->make('Signs the disbursement voucher', [
            'designation_id' => Designation::factory()->create()->id,
        ]);

        $this->actingAs($this->owner)
            ->post(route('ipcrs.items.catalog', $this->ipcr), ['job_function_ids' => [$function->id]]);

        $this->assertSame(0, $this->ipcr->items()->count());
    }

    public function test_a_retired_function_is_still_refused(): void
    {
        $function = $this->make('The old way', [
            'position_id' => $this->theirs->id, 'is_active' => false,
        ]);

        $this->actingAs($this->owner)
            ->post(route('ipcrs.items.catalog', $this->ipcr), ['job_function_ids' => [$function->id]]);

        $this->assertSame(0, $this->ipcr->items()->count());
    }
}
