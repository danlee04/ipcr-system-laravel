<?php

namespace App\Http\Controllers;

use App\Enums\FunctionCategory;
use App\Http\Requests\StoreIpcrItemRequest;
use App\Http\Requests\UpdateIpcrItemRequest;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\JobFunction;
use Illuminate\Http\RedirectResponse;

class IpcrItemController extends Controller
{
    /** Add one function/output line to a draft or returned IPCR. */
    public function store(StoreIpcrItemRequest $request, Ipcr $ipcr): RedirectResponse
    {
        $this->authorize('update', $ipcr);
        abort_unless($ipcr->isEditableByOwner(), 403, 'This IPCR can no longer be edited.');

        $data = $request->validated();

        // "Common" is a pool, not a rated category. A line picked from it is
        // filed under whichever category HR assigned to the function; without
        // one it cannot be rated at all, so it is refused here rather than
        // landing in a bucket the calculator ignores.
        if (($data['category'] ?? null) === FunctionCategory::Common->value) {
            $function = isset($data['job_function_id'])
                ? JobFunction::find($data['job_function_id'])
                : null;

            $resolved = $function?->ratingCategory();

            if ($resolved === null) {
                return back()->with('error', $function === null
                    ? 'Pick a category of Strategic, Core or Support for this function.'
                    : "\"{$function->title}\" has not been filed under a rated category yet. Ask HR to set one on the Functions screen.");
            }

            $data['category'] = $resolved->value;
        }

        // Left blank, the weight takes whatever the category has not spent.
        // The first line takes all 100, and each one after takes the
        // remainder, so the total is right at every point rather than only
        // once somebody has done the arithmetic.
        if (($data['weight'] ?? null) === null || $data['weight'] === '') {
            $data['weight'] = $this->remainingWeight($ipcr, $data['category']);
        }

        if ($overflow = $this->weightOverflow($ipcr, $data['category'], (float) $data['weight'])) {
            return back()->with('error', $overflow);
        }

        $data['sort_order'] = ((int) $ipcr->items()->max('sort_order')) + 1;

        $ipcr->items()->create($data);

        return back()->with('status', 'Function added.');
    }

    /** Edit an existing line - output, indicator, weight, actual accomplishment. */
    public function update(UpdateIpcrItemRequest $request, Ipcr $ipcr, IpcrItem $item): RedirectResponse
    {
        $this->authorize('update', $ipcr);
        abort_unless($ipcr->isEditableByOwner(), 403, 'This IPCR can no longer be edited.');
        abort_if($item->ipcr_id !== $ipcr->id, 404);

        $data = $request->validated();

        if ($overflow = $this->weightOverflow($ipcr, $item->category, (float) ($data['weight'] ?? 0), $item->id)) {
            return back()->with('error', $overflow);
        }

        $item->update($data);

        return back()->with('status', 'Function updated.');
    }

    /** Remove a line before submission. */
    public function destroy(Ipcr $ipcr, IpcrItem $item): RedirectResponse
    {
        $this->authorize('update', $ipcr);
        abort_unless($ipcr->isEditableByOwner(), 403, 'This IPCR can no longer be edited.');
        abort_if($item->ipcr_id !== $ipcr->id, 404);

        $item->delete();

        return back()->with('status', 'Function removed.');
    }

    /**
     * How much of this category's 100% is still unspent.
     *
     * Never negative: a category already full gives the next line nothing
     * rather than a number that would fail the guard below.
     */
    private function remainingWeight(Ipcr $ipcr, FunctionCategory|string $category): float
    {
        $category = $category instanceof FunctionCategory ? $category : FunctionCategory::from($category);

        $used = (float) $ipcr->items()->where('category', $category->value)->sum('weight');

        return max(0, round(100 - $used, 2));
    }

    /**
     * Would this weight push its category past 100%?
     *
     * Caught here as well as at submission because the mistake is far cheaper
     * to fix on the line that caused it than in a list of twenty. Returns the
     * message to show, or null when the weight fits.
     *
     * $ignoreItemId excludes the line being edited from the running total, so
     * re-saving an unchanged weight is never treated as an overflow.
     */
    private function weightOverflow(Ipcr $ipcr, FunctionCategory|string $category, float $weight, ?int $ignoreItemId = null): ?string
    {
        $category = $category instanceof FunctionCategory
            ? $category
            : FunctionCategory::from($category);

        $existing = (float) $ipcr->items()
            ->where('category', $category->value)
            ->when($ignoreItemId, fn ($query) => $query->whereKeyNot($ignoreItemId))
            ->sum('weight');

        // A hundredth of tolerance, matching the submit guard, so thirds that
        // add up to 100.00 are not rejected by floating point noise.
        if ($existing + $weight <= 100.01) {
            return null;
        }

        $remaining = max(0, round(100 - $existing, 2));
        $short = rtrim(rtrim(number_format($remaining, 2, '.', ''), '0'), '.');

        return "{$category->label()} already uses " . rtrim(rtrim(number_format($existing, 2, '.', ''), '0'), '.')
            . "% of its 100%. There is {$short}% left.";
    }
}
