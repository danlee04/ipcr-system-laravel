<?php

namespace Tests\Unit;

use App\Enums\FunctionCategory;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Services\RatingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A measure that does not apply.
 *
 * Not every output has all three dimensions - plenty have no Timeliness at
 * all - and IpcrItem has always said so: its own average divides by however
 * many marks are filled in, "treating those as zero would be wrong".
 *
 * RatingCalculator disagreed with it. It demanded all three before an IPCR
 * could be rated at all, and divided by three regardless. So a line with a
 * genuine n/a could never be assessed, and one that slipped through would be
 * scored as though the missing mark were a zero.
 */
class NotApplicableMeasureTest extends TestCase
{
    use RefreshDatabase;

    private RatingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = app(RatingCalculator::class);
    }

    private function ipcrRatedWith(array $marks, float $weight = 100): Ipcr
    {
        $ipcr = Ipcr::factory()->create();

        IpcrItem::factory()->create($marks + [
            'ipcr_id'  => $ipcr->id,
            'category' => FunctionCategory::Core,
            'weight'   => $weight,
        ]);

        return $ipcr->fresh()->load('items');
    }

    // -----------------------------------------------------------------
    // The two must agree
    // -----------------------------------------------------------------

    public function test_a_line_with_no_timeliness_can_still_be_rated(): void
    {
        $ipcr = $this->ipcrRatedWith([
            'quality_rating'    => 5,
            'efficiency_rating' => 4,
            'timeliness_rating' => null,
        ]);

        $breakdown = $this->calculator->for($ipcr);

        $this->assertTrue($breakdown->complete, 'A measure that does not apply must not block the whole IPCR.');
        $this->assertSame(4.5, $breakdown->core, 'The average is over the marks that apply, not over three.');
    }

    public function test_one_measure_on_its_own_is_enough(): void
    {
        $ipcr = $this->ipcrRatedWith([
            'quality_rating'    => 3,
            'efficiency_rating' => null,
            'timeliness_rating' => null,
        ]);

        $this->assertSame(3.0, $this->calculator->for($ipcr)->core);
    }

    /** The calculator and the line's own stored average must not disagree. */
    public function test_the_stored_average_matches_what_the_calculator_uses(): void
    {
        $ipcr = $this->ipcrRatedWith([
            'quality_rating'    => 5,
            'efficiency_rating' => 4,
            'timeliness_rating' => null,
        ]);

        $this->assertSame('4.500', $ipcr->items->first()->average_rating);
        $this->assertSame(4.5, $this->calculator->for($ipcr)->core);
    }

    // -----------------------------------------------------------------
    // Nothing at all is still nothing
    // -----------------------------------------------------------------

    public function test_a_line_with_no_marks_at_all_is_still_unrated(): void
    {
        $ipcr = $this->ipcrRatedWith([
            'quality_rating'    => null,
            'efficiency_rating' => null,
            'timeliness_rating' => null,
        ]);

        $breakdown = $this->calculator->for($ipcr);

        $this->assertFalse($breakdown->complete);
        $this->assertSame(1, $breakdown->unratedItemCount);
    }

    /** One rated line and one blank one: the blank is what holds it up. */
    public function test_a_blank_line_beside_a_rated_one_still_holds_the_ipcr(): void
    {
        $ipcr = $this->ipcrRatedWith(['quality_rating' => 5, 'efficiency_rating' => 5, 'timeliness_rating' => 5], 50);

        IpcrItem::factory()->create([
            'ipcr_id' => $ipcr->id, 'category' => FunctionCategory::Core, 'weight' => 50,
        ]);

        $this->assertFalse($this->calculator->for($ipcr->fresh()->load('items'))->complete);
    }

    // -----------------------------------------------------------------
    // Weighting still works over a mixed set
    // -----------------------------------------------------------------

    public function test_lines_with_different_measures_weigh_correctly_together(): void
    {
        $ipcr = $this->ipcrRatedWith([
            'quality_rating' => 5, 'efficiency_rating' => 5, 'timeliness_rating' => 5,
        ], 50);

        IpcrItem::factory()->create([
            'ipcr_id'           => $ipcr->id,
            'category'          => FunctionCategory::Core,
            'weight'            => 50,
            'quality_rating'    => 3,
            'efficiency_rating' => null,
            'timeliness_rating' => null,
        ]);

        // (5 * 50 + 3 * 50) / 100
        $this->assertSame(4.0, $this->calculator->for($ipcr->fresh()->load('items'))->core);
    }
}
