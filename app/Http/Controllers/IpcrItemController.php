<?php

namespace App\Http\Controllers;

use App\Enums\FunctionCategory;
use App\Http\Requests\StoreIpcrItemRequest;
use App\Http\Requests\UpdateIpcrItemRequest;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\JobFunction;
use App\Services\AccomplishmentWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class IpcrItemController extends Controller
{
    /** Add one function/output line to a draft or returned IPCR. */
    public function store(StoreIpcrItemRequest $request, Ipcr $ipcr): RedirectResponse
    {
        $this->authorize('update', $ipcr);
        abort_unless($ipcr->isEditableByOwner(), 403, 'This IPCR can no longer be edited.');

        $data = $request->validated();

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

    /**
     * Edit an existing line - output, indicator, weight, accomplishment.
     *
     * On a line that came from a graded catalog function the employee reports
     * figures instead of writing a sentence: the rubric turns those into the
     * accomplishment and the marks, so the same performance reads and scores
     * the same way whoever reports it.
     */
    public function update(
        UpdateIpcrItemRequest $request,
        Ipcr $ipcr,
        IpcrItem $item,
        AccomplishmentWriter $writer,
    ): RedirectResponse {
        $this->authorize('update', $ipcr);
        abort_unless($ipcr->isEditableByOwner(), 403, 'This IPCR can no longer be edited.');
        abort_if($item->ipcr_id !== $ipcr->id, 404);

        $data = $request->validated();
        $reported = $data['reported'] ?? [];
        unset($data['reported']);

        $rubric = $reported === [] ? null : $this->rubricOf($item);

        // Checked before anything is written, so a figure the rubric cannot
        // grade leaves the line exactly as it was rather than half saved.
        if ($rubric !== null && ($refused = $writer->ungradable($rubric, $reported)) !== []) {
            return back()->with('error', $this->ungradableMessage($refused));
        }

        if ($overflow = $this->weightOverflow($ipcr, $item->category, (float) ($data['weight'] ?? 0), $item->id)) {
            return back()->with('error', $overflow);
        }

        DB::transaction(function () use ($item, $data, $rubric, $reported, $writer): void {
            $item->update($data);

            if ($rubric !== null) {
                $writer->apply($item, $rubric, $reported);
            }
        });

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
     * The catalog function that grades this line, if one does.
     *
     * Null for a line typed by hand and for a catalog function nobody wrote a
     * rubric for: both of those are marked by the assessor, the way every
     * function worked before rubrics existed.
     */
    private function rubricOf(IpcrItem $item): ?JobFunction
    {
        $function = $item->jobFunction;

        if ($function === null) {
            return null;
        }

        $function->loadMissing('measures.bands');

        return $function->hasRubric() ? $function : null;
    }

    /** @param  list<string>  $measures */
    private function ungradableMessage(array $measures): string
    {
        $named = implode(' and ', $measures);
        $verb = count($measures) === 1 ? 'falls' : 'fall';

        return "The figure you reported for {$named} {$verb} outside every level of this function's rubric. "
            . 'Check it against the levels shown beside the field. Nothing was saved.';
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
