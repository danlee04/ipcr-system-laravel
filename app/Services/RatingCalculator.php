<?php

namespace App\Services;

use App\Enums\AdjectivalRating;
use App\Enums\FunctionCategory;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Support\RatingBreakdown;
use Illuminate\Support\Collection;

/**
 * Turns the Q/E/T marks on each line into the ratings that go on the IPCR.
 *
 * Three steps:
 *   1. each item        -> (quality + efficiency + timeliness) / 3
 *   2. each category    -> those averages, weighted by the item's weight
 *   3. the whole IPCR   -> the category ratings, weighted by category
 *
 * The category weights are not stored anywhere. They follow from what the
 * employee actually has:
 *
 *   no strategic items -> Core 80, Support 20
 *   with strategic     -> Strategic 40, Core 50, Support 10
 *
 * A category with no items has its share redistributed across the categories
 * that do have items, in proportion, so the weights always total 100. An
 * employee holding only core functions is therefore rated on core alone.
 *
 * "Common" is not a rating category. It marks a function as open to everyone
 * in the catalog; the function still belongs to core, support or strategic,
 * and that is the category the IPCR item carries.
 */
class RatingCalculator
{
    /** The three categories that carry weight, in report order. */
    private const RATED_CATEGORIES = [
        FunctionCategory::Strategic,
        FunctionCategory::Core,
        FunctionCategory::Support,
    ];

    private const WITH_STRATEGIC = ['strategic' => 40.0, 'core' => 50.0, 'support' => 10.0];

    private const WITHOUT_STRATEGIC = ['core' => 80.0, 'support' => 20.0];

    public function for(Ipcr $ipcr): RatingBreakdown
    {
        $items = $ipcr->items;

        $unrated = $items->reject(fn (IpcrItem $item): bool => $this->isRated($item))->count();

        if ($items->isEmpty() || $unrated > 0) {
            return RatingBreakdown::incomplete($unrated);
        }

        $byCategory = $items->groupBy(fn (IpcrItem $item): string => $this->categoryValue($item));

        $ratings = [];

        foreach (self::RATED_CATEGORIES as $category) {
            $lines = $byCategory->get($category->value);

            $ratings[$category->value] = $lines === null || $lines->isEmpty()
                ? null
                : $this->categoryRating($lines);
        }

        $weights = $this->weightsFor(array_keys(array_filter($ratings, fn (?float $r): bool => $r !== null)));

        $final = 0.0;

        foreach ($weights as $category => $weight) {
            $final += $ratings[$category] * ($weight / 100);
        }

        $final = $this->round($final);

        return new RatingBreakdown(
            complete: true,
            strategic: $ratings['strategic'],
            core: $ratings['core'],
            support: $ratings['support'],
            weights: $weights,
            finalNumeric: $final,
            finalAdjectival: AdjectivalRating::fromNumeric($final),
        );
    }

    /**
     * The category split, already reduced to the categories that have items
     * and renormalised back up to 100.
     *
     * @param  array<int, string>  $present
     * @return array<string, float>
     */
    public function weightsFor(array $present): array
    {
        if ($present === []) {
            return [];
        }

        $base = in_array('strategic', $present, true) ? self::WITH_STRATEGIC : self::WITHOUT_STRATEGIC;

        // Anything not in the base split (strategic when the split has none)
        // still needs a share, or its items would be rated at zero weight.
        foreach ($present as $category) {
            $base[$category] ??= 0.0;
        }

        $kept = array_intersect_key($base, array_flip($present));
        $total = array_sum($kept);

        if ($total <= 0.0) {
            // Every present category carries no base weight: share equally.
            $equal = $this->round(100 / count($present));

            return array_map(fn (): float => $equal, $kept);
        }

        $scaled = array_map(fn (float $weight): float => $this->round($weight * 100 / $total), $kept);

        return $this->orderByReport($scaled);
    }

    /** Weighted mean of the item averages; a plain mean when no weights are set. */
    private function categoryRating(Collection $items): float
    {
        $totalWeight = $items->sum(fn (IpcrItem $item): float => (float) ($item->weight ?? 0));

        if ($totalWeight <= 0.0) {
            return $this->round(
                $items->avg(fn (IpcrItem $item): float => $this->itemAverage($item))
            );
        }

        $weighted = $items->sum(
            fn (IpcrItem $item): float => $this->itemAverage($item) * (float) ($item->weight ?? 0)
        );

        return $this->round($weighted / $totalWeight);
    }

    /**
     * The mean of the marks that apply, not of three.
     *
     * Plenty of outputs have no Timeliness dimension at all. Dividing by three
     * regardless would score the missing one as a zero, which is not what an
     * n/a means - and IpcrItem::computeAverage() has always said so. This is
     * the same sum, so a line's stored average and the number the rating is
     * built from cannot disagree.
     */
    private function itemAverage(IpcrItem $item): float
    {
        $marks = $this->marksOf($item);

        return array_sum($marks) / count($marks);
    }

    /**
     * A line is rated once any one measure has a mark.
     *
     * Demanding all three made a legitimate n/a look like unfinished work and
     * held up the whole IPCR - nobody could assess it, and nothing on screen
     * explained why.
     */
    private function isRated(IpcrItem $item): bool
    {
        return $this->marksOf($item) !== [];
    }

    /** @return list<float> the marks actually given on this line */
    private function marksOf(IpcrItem $item): array
    {
        return array_values(array_map(
            fn ($mark): float => (float) $mark,
            array_filter(
                [$item->quality_rating, $item->efficiency_rating, $item->timeliness_rating],
                fn ($mark): bool => $mark !== null && $mark !== ''
            )
        ));
    }

    /** The category column accepts an enum or a plain string depending on the cast. */
    private function categoryValue(IpcrItem $item): string
    {
        return $item->category instanceof FunctionCategory
            ? $item->category->value
            : (string) $item->category;
    }

    /** @param array<string, float> $weights */
    private function orderByReport(array $weights): array
    {
        $ordered = [];

        foreach (self::RATED_CATEGORIES as $category) {
            if (array_key_exists($category->value, $weights)) {
                $ordered[$category->value] = $weights[$category->value];
            }
        }

        return $ordered;
    }

    /** The rating columns are decimal(4,3). */
    private function round(float $value): float
    {
        return round($value, 3);
    }
}
