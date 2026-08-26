<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FunctionCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreJobFunctionRequest;
use App\Http\Requests\Admin\UpdateJobFunctionRequest;
use App\Models\Designation;
use App\Models\JobFunction;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The master catalog of functions employees can pick from when building an
 * IPCR. Nothing here is ever added automatically - these are suggestions.
 *
 * How each category reaches an employee, per FunctionCatalogService:
 *   core      -> matched on the employee's single plantilla position
 *   strategic -> matched on their currently active designations
 *   support   -> matched on their currently active designations
 *   common    -> open to everyone, and carries the rated category it counts
 *                towards, because "common" is a pool, not a rating bucket
 */
class JobFunctionController extends Controller
{
    public function index(Request $request): View
    {
        $functions = JobFunction::query()
            ->with(['position', 'designation'])
            ->orderBy('category')
            ->orderBy('title')
            ->get();

        $positions = Position::query()->orderBy('title')->get();
        $designations = Designation::query()->orderBy('title')->get();

        // Common functions nobody can actually use yet. Surfaced here because
        // the failure otherwise only shows up when an employee tries to add one.
        $unfiled = $functions->filter(
            fn (JobFunction $f): bool => $f->category === FunctionCategory::Common && $f->rating_category === null
        );

        return view('admin.job-functions.index', compact('functions', 'positions', 'designations', 'unfiled'));
    }

    public function store(StoreJobFunctionRequest $request): RedirectResponse
    {
        $function = JobFunction::create($this->attributes($request->validated()) + ['is_active' => true]);

        return redirect()->route('admin.job-functions.index')
            ->with('status', 'Added function "' . $this->shorten($function->title) . '".');
    }

    public function update(UpdateJobFunctionRequest $request, JobFunction $jobFunction): RedirectResponse
    {
        $jobFunction->update($this->attributes($request->validated()));

        return redirect()->route('admin.job-functions.index')
            ->with('status', 'Updated function "' . $this->shorten($jobFunction->title) . '".');
    }

    public function setActive(Request $request, JobFunction $jobFunction): RedirectResponse
    {
        // An explicit value, not a toggle: two tabs disagreeing about the
        // current state would otherwise flip it the wrong way.
        $validated = $request->validate(['active' => ['required', 'boolean']]);

        $jobFunction->update(['is_active' => $validated['active']]);

        return redirect()->route('admin.job-functions.index')->with(
            'status',
            ($validated['active'] ? 'Activated' : 'Deactivated') . ' function "' . $this->shorten($jobFunction->title) . '".'
        );
    }

    /**
     * A catalog entry can always be deleted.
     *
     * ipcr_items.job_function_id is nullable and the line keeps its own copy
     * of the output and indicator, so removing the suggestion never touches an
     * IPCR that was built from it.
     */
    public function destroy(JobFunction $jobFunction): RedirectResponse
    {
        $title = $this->shorten($jobFunction->title);
        $jobFunction->delete();

        return redirect()->route('admin.job-functions.index')
            ->with('status', "Deleted function \"{$title}\". IPCRs already using it are untouched.");
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /** Clears the link that does not apply to the chosen category. */
    private function attributes(array $data): array
    {
        $category = FunctionCategory::from($data['category']);

        return [
            'category'          => $category,
            'title'             => $data['title'],
            'success_indicator' => $data['success_indicator'] ?? null,
            'default_weight'    => $data['default_weight'] ?? null,
            'position_id'       => $category === FunctionCategory::Core ? $data['position_id'] : null,
            'designation_id'    => in_array($category, [FunctionCategory::Strategic, FunctionCategory::Support], true)
                ? $data['designation_id']
                : null,
            'rating_category'   => $category === FunctionCategory::Common
                ? ($data['rating_category'] ?? null)
                : null,
        ];
    }

    /** Titles are free text and can run long; flash messages should not. */
    private function shorten(string $title): string
    {
        return mb_strlen($title) > 60 ? mb_substr($title, 0, 57) . '...' : $title;
    }
}
