<?php

namespace Tests\Unit;

use App\Enums\AdjectivalRating;
use App\Enums\FunctionCategory;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Services\RatingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The category weights are derived from what the employee actually has:
 *
 *   no strategic items -> Core 80, Support 20
 *   with strategic     -> Strategic 40, Core 50, Support 10
 *
 * A category with no items has its share redistributed across the categories
 * that do have items, in proportion, so the weights always total 100.
 */
class RatingCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function calculator(): RatingCalculator
    {
        return app(RatingCalculator::class);
    }

    /** @param array<int, array{category: FunctionCategory, weight: float|null, q: float, e: float, t: float}> $rows */
    private function ipcrWith(array $rows): Ipcr
    {
        $ipcr = Ipcr::factory()->create();

        foreach ($rows as $row) {
            IpcrItem::factory()->create([
                'ipcr_id'           => $ipcr->id,
                'category'          => $row['category'],
                'weight'            => $row['weight'],
                'quality_rating'    => $row['q'],
                'efficiency_rating' => $row['e'],
                'timeliness_rating' => $row['t'],
            ]);
        }

        return $ipcr->fresh('items');
    }

    private function row(FunctionCategory $category, ?float $weight, float $q, float $e, float $t): array
    {
        return ['category' => $category, 'weight' => $weight, 'q' => $q, 'e' => $e, 't' => $t];
    }

    // -----------------------------------------------------------------
    // Per item
    // -----------------------------------------------------------------

    public function test_an_items_average_is_the_mean_of_quality_efficiency_and_timeliness(): void
    {
        $breakdown = $this->calculator()->for($this->ipcrWith([
            $this->row(FunctionCategory::Core, 100, 5, 4, 3),
        ]));

        $this->assertSame(4.0, $breakdown->core);
    }

    // -----------------------------------------------------------------
    // Per category
    // -----------------------------------------------------------------

    public function test_a_category_rating_is_weighted_by_item_weight(): void
    {
        $breakdown = $this->calculator()->for($this->ipcrWith([
            $this->row(FunctionCategory::Core, 60, 5, 5, 5),
            $this->row(FunctionCategory::Core, 40, 3, 3, 3),
        ]));

        // (5 * 60 + 3 * 40) / 100
        $this->assertSame(4.2, $breakdown->core);
    }

    public function test_a_category_with_no_weights_falls_back_to_a_plain_average(): void
    {
        $breakdown = $this->calculator()->for($this->ipcrWith([
            $this->row(FunctionCategory::Core, null, 5, 5, 5),
            $this->row(FunctionCategory::Core, null, 3, 3, 3),
        ]));

        $this->assertSame(4.0, $breakdown->core);
    }

    public function test_a_category_with_no_items_has_a_null_rating(): void
    {
        $breakdown = $this->calculator()->for($this->ipcrWith([
            $this->row(FunctionCategory::Core, 100, 4, 4, 4),
        ]));

        $this->assertNull($breakdown->strategic);
        $this->assertNull($breakdown->support);
    }

    // -----------------------------------------------------------------
    // Category weights
    // -----------------------------------------------------------------

    public function test_without_strategic_items_the_split_is_core_80_support_20(): void
    {
        $breakdown = $this->calculator()->for($this->ipcrWith([
            $this->row(FunctionCategory::Core, 100, 5, 5, 5),
            $this->row(FunctionCategory::Support, 100, 3, 3, 3),
        ]));

        $this->assertSame(['core' => 80.0, 'support' => 20.0], $breakdown->weights);

        // 5 * 0.80 + 3 * 0.20
        $this->assertSame(4.6, $breakdown->finalNumeric);
    }

    public function test_with_strategic_items_the_split_is_strategic_40_core_50_support_10(): void
    {
        $breakdown = $this->calculator()->for($this->ipcrWith([
            $this->row(FunctionCategory::Strategic, 100, 5, 5, 5),
            $this->row(FunctionCategory::Core, 100, 4, 4, 4),
            $this->row(FunctionCategory::Support, 100, 3, 3, 3),
        ]));

        $this->assertSame(
            ['strategic' => 40.0, 'core' => 50.0, 'support' => 10.0],
            $breakdown->weights
        );

        // 5 * 0.40 + 4 * 0.50 + 3 * 0.10
        $this->assertSame(4.3, $breakdown->finalNumeric);
    }

    public function test_a_core_only_ipcr_puts_all_the_weight_on_core(): void
    {
        $breakdown = $this->calculator()->for($this->ipcrWith([
            $this->row(FunctionCategory::Core, 100, 4, 4, 5),
        ]));

        $this->assertSame(['core' => 100.0], $breakdown->weights);
        $this->assertSame($breakdown->core, $breakdown->finalNumeric);
    }

    /**
     * Strategic and core but no support: support's 10 is shared out in
     * proportion, so core keeps five ninths of it and strategic four ninths.
     */
    public function test_a_missing_category_has_its_weight_redistributed_in_proportion(): void
    {
        $breakdown = $this->calculator()->for($this->ipcrWith([
            $this->row(FunctionCategory::Strategic, 100, 5, 5, 5),
            $this->row(FunctionCategory::Core, 100, 4, 4, 4),
        ]));

        $this->assertSame(44.444, $breakdown->weights['strategic']);
        $this->assertSame(55.556, $breakdown->weights['core']);
        $this->assertEqualsWithDelta(100.0, array_sum($breakdown->weights), 0.001);

        // 5 * 0.44444 + 4 * 0.55556
        $this->assertEqualsWithDelta(4.444, $breakdown->finalNumeric, 0.001);
    }

    // -----------------------------------------------------------------
    // Final rating
    // -----------------------------------------------------------------

    public function test_the_adjectival_rating_follows_the_csc_scale(): void
    {
        $breakdown = $this->calculator()->for($this->ipcrWith([
            $this->row(FunctionCategory::Core, 100, 5, 5, 4),
        ]));

        $this->assertSame(AdjectivalRating::Outstanding, $breakdown->finalAdjectival);
    }

    public function test_a_low_score_is_rated_poor(): void
    {
        $breakdown = $this->calculator()->for($this->ipcrWith([
            $this->row(FunctionCategory::Core, 100, 1, 1, 1),
        ]));

        $this->assertSame(AdjectivalRating::Poor, $breakdown->finalAdjectival);
    }

    public function test_ratings_are_rounded_to_three_decimals(): void
    {
        // (4 + 5 + 5) / 3 = 4.6666...
        $breakdown = $this->calculator()->for($this->ipcrWith([
            $this->row(FunctionCategory::Core, 100, 4, 5, 5),
        ]));

        $this->assertSame(4.667, $breakdown->core);
    }

    // -----------------------------------------------------------------
    // Incomplete input
    // -----------------------------------------------------------------

    public function test_an_ipcr_with_no_items_cannot_be_rated(): void
    {
        $breakdown = $this->calculator()->for(Ipcr::factory()->create());

        $this->assertFalse($breakdown->complete);
        $this->assertNull($breakdown->finalNumeric);
    }

    /**
     * A partly rated IPCR must not produce a final number. Averaging only the
     * lines that happen to be filled in would quietly invent a rating.
     */
    public function test_an_ipcr_with_an_unrated_item_cannot_be_rated(): void
    {
        $ipcr = $this->ipcrWith([$this->row(FunctionCategory::Core, 100, 4, 4, 4)]);

        IpcrItem::factory()->create([
            'ipcr_id'           => $ipcr->id,
            'category'          => FunctionCategory::Core,
            'weight'            => 50,
            'quality_rating'    => null,
            'efficiency_rating' => null,
            'timeliness_rating' => null,
        ]);

        $breakdown = $this->calculator()->for($ipcr->fresh('items'));

        $this->assertFalse($breakdown->complete);
        $this->assertNull($breakdown->finalNumeric);
        $this->assertSame(1, $breakdown->unratedItemCount);
    }

    public function test_a_fully_rated_ipcr_is_complete(): void
    {
        $breakdown = $this->calculator()->for($this->ipcrWith([
            $this->row(FunctionCategory::Core, 100, 4, 4, 4),
        ]));

        $this->assertTrue($breakdown->complete);
        $this->assertSame(0, $breakdown->unratedItemCount);
    }
}
