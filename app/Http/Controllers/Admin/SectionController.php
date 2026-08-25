<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSectionRequest;
use App\Http\Requests\Admin\UpdateSectionRequest;
use App\Models\Section;
use App\Services\OrgDeletionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sections live inside a division and are managed from the divisions screen,
 * because a section cannot exist without its parent.
 *
 * The section head assignment matters as much as the division head:
 * IpcrRoutingService reads sections.section_head_employee_id to find the
 * assessor, and without it nobody in the section can submit an IPCR.
 */
class SectionController extends Controller
{
    public function __construct(private readonly OrgDeletionGuard $guard) {}

    public function store(StoreSectionRequest $request): RedirectResponse
    {
        $section = Section::create($request->validated() + ['is_active' => true]);

        return redirect()->route('admin.divisions.index')
            ->with('status', "Created section \"{$section->name}\".");
    }

    public function update(UpdateSectionRequest $request, Section $section): RedirectResponse
    {
        $section->update($request->validated());

        return redirect()->route('admin.divisions.index')
            ->with('status', "Updated section \"{$section->name}\".");
    }

    public function setActive(Request $request, Section $section): RedirectResponse
    {
        // An explicit value, not a toggle: two tabs disagreeing about the
        // current state would otherwise flip it the wrong way.
        $validated = $request->validate(['active' => ['required', 'boolean']]);

        $section->update(['is_active' => $validated['active']]);

        return redirect()->route('admin.divisions.index')->with(
            'status',
            ($validated['active'] ? 'Activated' : 'Deactivated') . " section \"{$section->name}\"."
        );
    }

    public function destroy(Section $section): RedirectResponse
    {
        // Re-checked here, not just in the view: a stale tab could otherwise
        // delete a record that gained a reference in the meantime.
        $report = $this->guard->for($section);

        if (! $report->deletable) {
            return redirect()->route('admin.divisions.index')->with('error', $report->message());
        }

        $name = $section->name;
        $section->delete();

        return redirect()->route('admin.divisions.index')
            ->with('status', "Deleted section \"{$name}\".");
    }
}
