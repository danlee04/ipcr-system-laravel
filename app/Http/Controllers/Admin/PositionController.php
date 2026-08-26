<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePositionRequest;
use App\Http\Requests\Admin\UpdatePositionRequest;
use App\Models\Position;
use App\Services\OrgDeletionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A position is the single plantilla post an employee holds, and the source of
 * their CORE job functions. Positions is the default tab on the Positions
 * page, so these redirects carry no tab parameter.
 */
class PositionController extends Controller
{
    public function __construct(private readonly OrgDeletionGuard $guard) {}

    public function store(StorePositionRequest $request): RedirectResponse
    {
        $position = Position::create($request->validated() + ['is_active' => true]);

        return redirect()->route('admin.positions.index')
            ->with('status', "Created position \"{$position->title}\".");
    }

    public function update(UpdatePositionRequest $request, Position $position): RedirectResponse
    {
        $position->update($request->validated());

        return redirect()->route('admin.positions.index')
            ->with('status', "Updated position \"{$position->title}\".");
    }

    public function setActive(Request $request, Position $position): RedirectResponse
    {
        // An explicit value, not a toggle: two tabs disagreeing about the
        // current state would otherwise flip it the wrong way.
        $validated = $request->validate(['active' => ['required', 'boolean']]);

        $position->update(['is_active' => $validated['active']]);

        return redirect()->route('admin.positions.index')->with(
            'status',
            ($validated['active'] ? 'Activated' : 'Deactivated') . " position \"{$position->title}\"."
        );
    }

    public function destroy(Position $position): RedirectResponse
    {
        // Re-checked here, not just in the view: a stale tab could otherwise
        // delete a record that gained a reference in the meantime.
        $report = $this->guard->for($position);

        if (! $report->deletable) {
            return redirect()->route('admin.positions.index')->with('error', $report->message());
        }

        $title = $position->title;
        $position->delete();

        return redirect()->route('admin.positions.index')
            ->with('status', "Deleted position \"{$title}\".");
    }
}
