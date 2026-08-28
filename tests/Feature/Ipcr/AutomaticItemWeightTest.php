<?php

namespace Tests\Feature\Ipcr;

use App\Enums\FunctionCategory;
use App\Enums\IpcrStatus;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrPeriod;
use App\Models\JobFunction;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The weight of a line is worked out, not typed.
 *
 * Within a category the lines share the hundred equally: three core functions
 * are 33.33, 33.33 and 33.34. That is what "automatic" has to mean, because
 * the alternative - the first line taking all hundred and every line after it
 * taking nothing - passes the submit guard while quietly making every function
 * but the first count for nothing at all.
 */
class AutomaticItemWeightTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Ipcr $ipcr;

    private Position $position;

    protected function setUp(): void
    {
        parent::setUp();

        $this->position = Position::factory()->create();

        $this->owner = User::factory()->create();
        $employee = Employee::factory()->create([
            'user_id' => $this->owner->id, 'position_id' => $this->position->id,
        ]);

        $this->ipcr = Ipcr::factory()->create([
            'employee_id'    => $employee->id,
            'ipcr_period_id' => IpcrPeriod::factory()->create(['status' => 'open'])->id,
            'status'         => IpcrStatus::Draft,
        ]);

        $this->owner = $this->owner->fresh();
    }

    /**
     * Everything comes off the catalog now - there is no hand-typed line - so
     * a function has to exist and reach this employee before it can be added.
     */
    private function add(string $output, FunctionCategory $category = FunctionCategory::Core, array $extra = []): void
    {
        $function = JobFunction::create([
            'category'    => $category,
            'position_id' => $this->position->id,
            'title'       => $output,
            'is_active'   => true,
        ]);

        $this->actingAs($this->owner)
            ->post(route('ipcrs.items.catalog', $this->ipcr), array_merge([
                'job_function_ids' => [$function->id],
            ], $extra))
            ->assertSessionHasNoErrors();
    }

    /** @return array<string, string> output => weight */
    private function weights(): array
    {
        return $this->ipcr->items()->orderBy('id')->pluck('weight', 'output')->all();
    }

    public function test_one_function_carries_the_whole_category(): void
    {
        $this->add('Only one');

        $this->assertSame(['Only one' => '100.00'], $this->weights());
    }

    public function test_two_functions_split_it_evenly(): void
    {
        $this->add('First');
        $this->add('Second');

        $this->assertSame(['First' => '50.00', 'Second' => '50.00'], $this->weights());
    }

    /** The odd hundredth goes to the last line, so the total is exactly 100. */
    public function test_three_functions_split_it_to_the_hundredth(): void
    {
        $this->add('First');
        $this->add('Second');
        $this->add('Third');

        $this->assertSame(
            ['First' => '33.33', 'Second' => '33.33', 'Third' => '33.34'],
            $this->weights()
        );
    }

    public function test_the_category_still_totals_one_hundred(): void
    {
        $this->add('First');
        $this->add('Second');
        $this->add('Third');

        $this->assertSame(
            100.0,
            round((float) $this->ipcr->items()->sum('weight'), 2)
        );
    }

    /** Each category is its own hundred; adding to one leaves the other alone. */
    public function test_the_categories_do_not_disturb_each_other(): void
    {
        $this->add('Core one');
        $this->add('Support one', FunctionCategory::Support);
        $this->add('Core two');

        $this->assertSame([
            'Core one'    => '50.00',
            'Support one' => '100.00',
            'Core two'    => '50.00',
        ], $this->weights());
    }

    /** Removing one gives its share back to the others. */
    public function test_removing_a_function_shares_its_weight_out_again(): void
    {
        $this->add('First');
        $this->add('Second');
        $this->add('Third');

        $second = $this->ipcr->items()->where('output', 'Second')->first();

        $this->actingAs($this->owner)
            ->delete(route('ipcrs.items.destroy', [$this->ipcr, $second]));

        $this->assertSame(['First' => '50.00', 'Third' => '50.00'], $this->weights());
    }

    /**
     * There is no weight field, and sending one anyway changes nothing.
     *
     * The form does not ask, so this is a crafted request rather than a
     * mistake - and a weight that could be smuggled in would let one line
     * quietly outrank the rest of its category.
     */
    public function test_a_weight_sent_anyway_is_ignored(): void
    {
        $this->add('Heavy', FunctionCategory::Core, ['weight' => 70]);
        $this->add('Light');

        $this->assertSame(['Heavy' => '50.00', 'Light' => '50.00'], $this->weights());
    }

    /** Nor can one be smuggled in by editing a line afterwards. */
    public function test_a_weight_cannot_be_edited_in_either(): void
    {
        $this->add('First');
        $this->add('Second');

        $first = $this->ipcr->items()->where('output', 'First')->first();

        $this->actingAs($this->owner)->put(route('ipcrs.items.update', [$this->ipcr, $first]), [
            'output' => 'First', 'weight' => 90,
        ])->assertSessionHasNoErrors();

        $this->assertSame(['First' => '50.00', 'Second' => '50.00'], $this->weights());
    }

    /** And the form does not offer one to type. */
    public function test_the_form_does_not_ask_for_a_weight(): void
    {
        $this->add('First');

        $html = $this->actingAs($this->owner)
            ->get(route('ipcrs.show', $this->ipcr))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('name="weight"', $html);
    }
}
