<?php

namespace App\Http\Controllers;

use App\Enums\FunctionCategory;
use App\Http\Requests\StoreIpcrItemRequest;
use App\Http\Requests\UpdateIpcrItemRequest;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use Illuminate\Http\RedirectResponse;

class IpcrItemController extends Controller
{
    /** Add one function/output line to a draft or returned IPCR. */
    public function store(StoreIpcrItemRequest $request, Ipcr $ipcr): RedirectResponse
    {
        $this->authorize('update', $ipcr);
        abort_unless($ipcr->isEditableByOwner(), 403, 'This IPCR can no longer be edited.');

        $data = $request->validated();

        if ($overflow = $this->weightOverflow($ipcr, $data['category'], (float) ($data['weight'] ?? 0))) {
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
