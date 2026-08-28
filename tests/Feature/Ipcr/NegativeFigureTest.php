<?php

namespace Tests\Feature\Ipcr;

use App\Enums\FunctionCategory;
use App\Enums\IpcrMode;
use App\Enums\IpcrStatus;
use App\Models\Employee;
use App\Models\FunctionMeasure;
use App\Models\FunctionRatingBand;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\JobFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A figure below zero.
 *
 * On a timeliness ladder counted from the deadline it is the whole point:
 * minus five is five days early and earns the top mark. Anywhere else it is a
 * typo, and one that used to go straight through - a percentage ladder is open
 * at the bottom, so minus five landed in the lowest band and the sheet read
 * "-5% of DTR submitted". A reported-only figure had nothing to check it
 * against at all.
 *
 * The scale itself says which it is: a ladder written across zero has a
 * negative bound somewhere in it, and no other kind does.
 */
class NegativeFigureTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Ipcr $ipcr;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $this->owner = $user->fresh();
        $this->ipcr = Ipcr::factory()->create([
            'employee_id' => $employee->id,
            'status'      => IpcrStatus::Draft,
            'mode'        => IpcrMode::WithAccomplishment,
        ]);
    }

    /**
     * @param  list<array{int, ?float, ?float}>  $bands  level, from, to
     */
    private function graded(string $measure, array $bands, string $unit = '%', ?string $template = null): JobFunction
    {
        $function = JobFunction::create([
            'category'                => FunctionCategory::Core,
            'title'                   => 'A function',
            'accomplishment_template' => $template,
            'is_active'               => true,
        ]);

        $row = FunctionMeasure::create([
            'job_function_id' => $function->id,
            'measure'         => $measure,
            'answer'          => 'number',
            'unit'            => $unit,
        ]);

        foreach ($bands as [$level, $min, $max]) {
            FunctionRatingBand::create([
                'function_measure_id' => $row->id, 'level' => $level,
                'description' => "Level {$level}", 'min_value' => $min, 'max_value' => $max,
            ]);
        }

        return $function->load('measures.bands');
    }

    /** The usual percentage ladder: open at the bottom, never below zero. */
    private function percentage(): JobFunction
    {
        return $this->graded('efficiency', [
            [5, 100, null], [4, 90, 99.99], [3, 80, 89.99], [2, 70, 79.99], [1, null, 69.99],
        ], '%', '{e}% of DTR submitted');
    }

    /** Counted from the deadline: minus is early, and that is the point. */
    private function fromTheDeadline(): JobFunction
    {
        return $this->graded('timeliness', [
            [5, null, -5], [4, -4, -3], [3, -2, 0], [2, 1, 7], [1, 8, null],
        ], 'days', 'Submitted {t_when}');
    }

    private function line(JobFunction $function): IpcrItem
    {
        return IpcrItem::factory()->create([
            'ipcr_id'         => $this->ipcr->id,
            'job_function_id' => $function->id,
            'category'        => FunctionCategory::Core,
        ]);
    }

    private function report(IpcrItem $item, array $reported): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->owner)->put(
            route('ipcrs.items.update', [$this->ipcr, $item]),
            ['output' => $item->output, 'reported' => $reported],
        );
    }

    // -----------------------------------------------------------------
    // Where minus means something
    // -----------------------------------------------------------------

    public function test_a_ladder_written_across_zero_takes_a_negative(): void
    {
        $item = $this->line($this->fromTheDeadline());

        $this->report($item, ['timeliness' => ['value' => -5]])->assertSessionHasNoErrors();

        $item = $item->fresh();
        $this->assertSame('5.00', $item->timeliness_rating);
        $this->assertSame('Submitted 5 days before', $item->actual_accomplishment);
    }

    // -----------------------------------------------------------------
    // Where it does not
    // -----------------------------------------------------------------

    /**
     * A percentage ladder is open at the bottom so that anything under 70
     * scores a one. It was letting minus five through the same door.
     */
    public function test_a_percentage_refuses_a_negative(): void
    {
        $item = $this->line($this->percentage());

        $this->report($item, ['efficiency' => ['value' => -5]])
            ->assertSessionHas('error', fn (string $m): bool => str_contains($m, 'below zero'));

        $item = $item->fresh();
        $this->assertNull($item->efficiency_rating, 'Nothing was saved.');
        $this->assertNull($item->actual_accomplishment);
    }

    /** And zero itself is a real answer, not a refusal. */
    public function test_zero_is_still_accepted(): void
    {
        $item = $this->line($this->percentage());

        $this->report($item, ['efficiency' => ['value' => 0]])->assertSessionHasNoErrors();

        $this->assertSame('1.00', $item->fresh()->efficiency_rating);
    }

    /**
     * A reported-only figure has no ladder at all, so there was nothing to
     * catch it. The sheet simply printed "-5%".
     */
    public function test_a_reported_only_figure_refuses_a_negative(): void
    {
        $function = JobFunction::create([
            'category'                => FunctionCategory::Core,
            'title'                   => 'A function',
            'accomplishment_template' => '{e}% of reports submitted',
            'is_active'               => true,
        ]);

        FunctionMeasure::create([
            'job_function_id' => $function->id, 'measure' => 'efficiency',
            'answer' => 'number', 'unit' => '%',
        ]);

        $item = $this->line($function->load('measures.bands'));

        $this->report($item, ['efficiency' => ['value' => -5]])
            ->assertSessionHas('error', fn (string $m): bool => str_contains($m, 'below zero'));

        $this->assertNull($item->fresh()->actual_accomplishment);
    }

    /** The message names the measure, so a long rubric says which one. */
    public function test_the_message_names_the_measure(): void
    {
        $item = $this->line($this->percentage());

        $this->report($item, ['efficiency' => ['value' => -5]])
            ->assertSessionHas('error', fn (string $m): bool => str_contains($m, 'Efficiency'));
    }

    /** A count could already not go below zero, and still cannot. */
    public function test_a_count_below_zero_is_refused_by_the_form(): void
    {
        $function = JobFunction::create([
            'category' => FunctionCategory::Core, 'title' => 'A function', 'is_active' => true,
        ]);

        FunctionMeasure::create([
            'job_function_id' => $function->id, 'measure' => 'efficiency',
            'answer' => 'count', 'unit' => null,
        ]);

        $item = $this->line($function->load('measures.bands'));

        $this->report($item, ['efficiency' => ['count' => -5, 'total' => 7]])
            ->assertSessionHasErrors('reported.efficiency.count');
    }
}
