<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDesignationRequest;
use App\Http\Requests\Admin\UpdateDesignationRequest;
use App\Models\Designation;
use App\Services\OrgDeletionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A designation is an extra assignment beyond the plantilla position, and the
 * source of an employee's STRATEGIC and SUPPORT job functions. An employee may
 * hold several at once, which is why it is a separate model from Position.
 */
class DesignationController extends Controller
{
    public function __construct(private readonly OrgDeletionGuard $guard) {}

    /** Every redirect carries the tab so the administrator lands back where they were. */
    private function backToTab(string $message, string $key = 'status'): RedirectResponse
    {
        return redirect()
            ->route('admin.positions.index', ['tab' => 'designations'])
            ->with($key, $message);
    }

    public function store(StoreDesignationRequest $request): RedirectResponse
    {
        $designation = Designation::create($request->validated() + ['is_active' => true]);

        return $this->backToTab("Created designation \"{$designation->title}\".");
    }

    public function update(UpdateDesignationRequest $request, Designation $designation): RedirectResponse
    {
        $designation->update($request->validated());

        return $this->backToTab("Updated designation \"{$designation->title}\".");
    }

    public function setActive(Request $request, Designation $designation): RedirectResponse
    {
        // An explicit value, not a toggle: two tabs disagreeing about the
        // current state would otherwise flip it the wrong way.
        $validated = $request->validate(['active' => ['required', 'boolean']]);

        $designation->update(['is_active' => $validated['active']]);

        return $this->backToTab(
            ($validated['active'] ? 'Activated' : 'Deactivated') . " designation \"{$designation->title}\"."
        );
    }

    public function destroy(Designation $designation): RedirectResponse
    {
        // Re-checked here, not just in the view: a stale tab could otherwise
        // delete a record that gained a reference in the meantime.
        $report = $this->guard->for($designation);

        if (! $report->deletable) {
            return $this->backToTab($report->message(), 'error');
        }

        $title = $designation->title;
        $designation->delete();

        return $this->backToTab("Deleted designation \"{$title}\".");
    }
}
