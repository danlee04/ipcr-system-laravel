<?php

namespace App\Support;

use App\Enums\AdjectivalRating;

/**
 * The result of rating one IPCR: the per-category numbers, the category
 * weights they were combined with, and the final rating.
 *
 * When $complete is false the IPCR could not be rated - either it has no items
 * or some line still has no Q/E/T - and every numeric field is null. A partial
 * rating is worse than none, because it looks like a real one.
 */
readonly class RatingBreakdown
{
    /** @param array<string, float> $weights category name => percentage, totalling 100 */
    public function __construct(
        public bool $complete,
        public ?float $strategic,
        public ?float $core,
        public ?float $support,
        public array $weights,
        public ?float $finalNumeric,
        public ?AdjectivalRating $finalAdjectival,
        public int $unratedItemCount = 0,
    ) {}

    /** An IPCR that cannot be rated yet. */
    public static function incomplete(int $unratedItemCount): self
    {
        return new self(
            complete: false,
            strategic: null,
            core: null,
            support: null,
            weights: [],
            finalNumeric: null,
            finalAdjectival: null,
            unratedItemCount: $unratedItemCount,
        );
    }

    /**
     * The columns to write onto the ipcrs row.
     *
     * The split is stored alongside the ratings, not just used and thrown
     * away. It is derived from which categories the employee had, so an
     * approved IPCR has to carry the numbers it was actually rated with -
     * otherwise nobody can reconstruct the final rating a year later. A
     * category with no items is written as zero, overwriting the 30/50/20
     * defaults the migration puts on these columns.
     */
    public function toIpcrColumns(): array
    {
        return [
            'strategic_rating'        => $this->strategic,
            'core_rating'             => $this->core,
            'support_rating'          => $this->support,
            'strategic_weight'        => $this->weights['strategic'] ?? 0,
            'core_weight'             => $this->weights['core'] ?? 0,
            'support_weight'          => $this->weights['support'] ?? 0,
            'final_numerical_rating'  => $this->finalNumeric,
            'final_adjectival_rating' => $this->finalAdjectival?->value,
        ];
    }
}
