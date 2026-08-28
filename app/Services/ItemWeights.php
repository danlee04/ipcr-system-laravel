<?php

namespace App\Services;

use App\Enums\FunctionCategory;
use App\Models\Ipcr;
use App\Models\IpcrItem;

/**
 * The hundred of a category, shared out across its lines.
 *
 * Nobody types a weight. Every line in a category carries the same share of
 * its hundred, worked out again whenever a line is added or removed, so the
 * category is right at every point rather than only once somebody has done the
 * arithmetic.
 *
 * It used to be prefilled instead: the first line took all hundred and every
 * line after it took what was left, which was nothing. That totalled a hundred
 * and so passed every check, while quietly making all but the first function
 * count for nought.
 */
class ItemWeights
{
    /** Shares are counted in hundredths, which is what makes them exact. */
    private const WHOLE = 10000;

    public function share(Ipcr $ipcr, FunctionCategory $category): void
    {
        $lines = $ipcr->items()
            ->where('category', $category->value)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($lines->isEmpty()) {
            return;
        }

        $each = intdiv(self::WHOLE, $lines->count());

        // Whatever will not divide goes to the last line. A third of a hundred
        // is 33.33, 33.33 and 33.34 - never three of 33.33, which is 99.99.
        $remainder = self::WHOLE - $each * $lines->count();

        $last = $lines->count() - 1;

        foreach ($lines as $index => $line) {
            $hundredths = $each + ($index === $last ? $remainder : 0);

            $this->set($line, $hundredths / 100);
        }
    }

    /** Every category at once, for when a whole IPCR needs settling. */
    public function shareAll(Ipcr $ipcr): void
    {
        foreach (FunctionCategory::cases() as $category) {
            $this->share($ipcr, $category);
        }
    }

    /** Written only when it differs, so an untouched line is not re-saved. */
    private function set(IpcrItem $line, float $weight): void
    {
        if (round((float) $line->weight, 2) === round($weight, 2)) {
            return;
        }

        $line->update(['weight' => $weight]);
    }
}
