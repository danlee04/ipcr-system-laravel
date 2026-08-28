<?php

namespace App\Http\Controllers;

use App\Enums\FunctionCategory;
use App\Http\Requests\StoreIpcrItemRequest;
use App\Http\Requests\UpdateIpcrItemRequest;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use App\Models\JobFunction;
use App\Services\AccomplishmentWriter;
use App\Services\FunctionCatalogService;
use App\Services\ItemWeights;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IpcrItemController extends Controller
{
    /** Add one function/output line to a draft or returned IPCR. */
    public function store(StoreIpcrItemRequest $request, Ipcr $ipcr, ItemWeights $weights): RedirectResponse
    {
        $this->authorize('update', $ipcr);
        abort_unless($ipcr->isEditableByOwner(), 403, 'This IPCR can no longer be edited.');

        $data = $request->validated();
        $data['sort_order'] = ((int) $ipcr->items()->max('sort_order')) + 1;

        $ipcr->items()->create($data);

        // The category's hundred is shared again across everything in it, this
        // new line included. Nobody types a weight, so nothing here can be
        // left over or spent twice.
        $weights->share($ipcr, FunctionCategory::from($data['category']));

        return back()->with('status', 'Function added.');
    }

    /**
     * Add every function ticked off the catalog, in one go.
     *
     * Separate from store() because it asks a different question. That one
     * takes an output somebody typed; this one takes a list of ids and reads
     * everything else off the catalog, so the wording on the IPCR matches the
     * wording HR wrote once.
     */
    public function addFromCatalog(
        Request $request,
        Ipcr $ipcr,
        FunctionCatalogService $catalog,
        ItemWeights $weights,
    ): RedirectResponse {
        $this->authorize('update', $ipcr);
        abort_unless($ipcr->isEditableByOwner(), 403, 'This IPCR can no longer be edited.');

        $validated = $request->validate([
            'job_function_ids'   => ['required', 'array', 'min:1'],
            'job_function_ids.*' => ['integer'],
        ], [], ['job_function_ids' => 'functions']);

        // Checked against this employee's own catalog rather than trusted. The
        // form sends a list of numbers, and a crafted one can name anything.
        $offered = $catalog->availableFor($ipcr->employee)->all();

        $chosen = collect($validated['job_function_ids'])
            ->unique()
            ->map(fn (int $id): ?JobFunction => $offered->get($id))
            ->filter();

        // Already there is not an error, it is a second click.
        $already = $ipcr->items()->whereNotNull('job_function_id')->pluck('job_function_id')->all();
        $adding = $chosen->reject(fn (JobFunction $f): bool => in_array($f->id, $already, true))->values();

        if ($adding->isEmpty()) {
            return back()->with('error', 'Nothing new to add - those are already on your IPCR.');
        }

        DB::transaction(function () use ($ipcr, $adding, $weights): void {
            $sort = (int) $ipcr->items()->max('sort_order');

            foreach ($adding as $function) {
                $ipcr->items()->create([
                    'job_function_id'   => $function->id,
                    'category'          => $function->category,
                    'output'            => $function->title,
                    'success_indicator' => $function->success_indicator,
                    'sort_order'        => ++$sort,
                ]);
            }

            // Once per category, after all of them are in, rather than once
            // per line - the shares would otherwise be worked out and thrown
            // away again for every function added.
            foreach ($adding->map(fn (JobFunction $f) => $f->category)->unique() as $category) {
                $weights->share($ipcr, $category);
            }
        });

        $count = $adding->count();

        return back()->with('status', $count === 1
            ? 'Function added.'
            : "{$count} functions added.");
    }

    /**
     * Edit an existing line - output, indicator, accomplishment.
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

        DB::transaction(function () use ($item, $data, $rubric, $reported, $writer): void {
            $item->update($data);

            if ($rubric !== null) {
                $writer->apply($item, $rubric, $reported);
            }
        });

        return back()->with('status', 'Function updated.');
    }

    /** Remove a line before submission. */
    public function destroy(Ipcr $ipcr, IpcrItem $item, ItemWeights $weights): RedirectResponse
    {
        $this->authorize('update', $ipcr);
        abort_unless($ipcr->isEditableByOwner(), 403, 'This IPCR can no longer be edited.');
        abort_if($item->ipcr_id !== $ipcr->id, 404);

        $category = $item->category;

        $item->delete();

        // Its share goes back to whatever is left in the category.
        $weights->share($ipcr, $category);

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

}
