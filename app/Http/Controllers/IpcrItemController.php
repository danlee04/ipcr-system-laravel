<?php

namespace App\Http\Controllers;

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

        $item->update($request->validated());

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
}
