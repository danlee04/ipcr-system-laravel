<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * The four numbers a group of names is worth reporting by.
 *
 * Computed for the whole hospital and again for each division and section, so
 * the same reading applies at every level of the sheet.
 */
final class SummaryTally
{
    private function __construct(
        public readonly int $expected,
        public readonly int $submitted,
        public readonly int $approved,
        public readonly int $late,
        public readonly ?float $average,
    ) {}

    /** @param  Collection<int, SummaryRow>  $rows */
    public static function of(Collection $rows): self
    {
        $ratings = $rows->map(fn (SummaryRow $row): ?float => $row->approvedRating())
            ->filter(fn (?float $rating): bool => $rating !== null);

        return new self(
            expected: $rows->count(),
            submitted: $rows->filter(fn (SummaryRow $row): bool => $row->isSubmitted())->count(),
            approved: $rows->filter(fn (SummaryRow $row): bool => $row->isApproved())->count(),
            late: $rows->filter(fn (SummaryRow $row): bool => $row->isLate())->count(),

            // Null, not zero. Nobody approved yet is not an average of nought,
            // and printing 0.00 beside a section would read as a failing one.
            average: $ratings->isEmpty() ? null : round($ratings->avg(), 2),
        );
    }

    /**
     * How many are still holding on to theirs.
     *
     * Counted against submitted rather than started, because a draft nobody
     * has sent is exactly as useful to HR as a blank one.
     */
    public function outstanding(): int
    {
        return $this->expected - $this->submitted;
    }
}
