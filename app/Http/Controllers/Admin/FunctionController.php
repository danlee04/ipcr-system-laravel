<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FunctionCategory;
use App\Http\Controllers\Concerns\RendersLiveLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreJobFunctionRequest;
use App\Http\Requests\Admin\UpdateJobFunctionRequest;
use App\Models\Designation;
use App\Models\Division;
use App\Models\JobFunction;
use App\Models\Position;
use App\Models\Section;
use App\Services\RubricSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The master catalog of functions employees can pick from when building an
 * IPCR. Nothing here is ever added automatically - these are suggestions.
 *
 * Two things are asked of every entry, and they are separate questions:
 *
 *   category   -> what kind of work it is: core, support or strategic
 *   applies to -> who it reaches: a position, a designation, or everyone
 */
class FunctionController extends Controller
{
    use RendersLiveLists;

    /** Rows per page, matching the other admin lists. */
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->value();
        $category = FunctionCategory::tryFrom((string) $request->query('category'));

        $functions = JobFunction::query()
            ->with(['position.section.division', 'designation', 'measures.bands'])
            ->when($search, function (Builder $query, string $term): void {
                $like = '%' . $term . '%';
                $query->where(fn (Builder $inner) => $inner->where('title', 'like', $like)
                    ->orWhere('success_indicator', 'like', $like));
            })
            ->when($category, fn (Builder $query) => $query->where('category', $category))
            ->when($request->integer('position'), $this->throughPosition(...))
            ->when($request->integer('section'), fn (Builder $q, int $id) => $this->throughPosition(
                $q, null, fn (Builder $p) => $p->where('section_id', $id)
            ))
            ->when($request->integer('division'), fn (Builder $q, int $id) => $this->throughPosition(
                $q, null, fn (Builder $p) => $p->whereHas('section', fn (Builder $s) => $s->where('division_id', $id))
            ))
            ->orderBy('category')
            ->orderBy('title')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $positions = Position::query()->with('section')->orderBy('title')->get();
        $designations = Designation::query()->orderBy('title')->get();
        $divisions = Division::query()->orderBy('name')->get();
        $sections = Section::query()->with('division')->orderBy('name')->get();

        return $this->liveList($request, 'admin.functions.index', 'admin.functions.rows', compact(
            'functions', 'positions', 'designations', 'divisions', 'sections',
            'search', 'category'
        ));
    }

    /**
     * Narrow to functions reached through a position, without hiding the ones
     * that reach people some other way.
     *
     * A division or section is a fact about a position. A function that has no
     * position - one open to everyone, or one attached to a designation - sits in
     * no division at all, so narrowing by division cannot say anything about
     * it. Hiding those would make the filter look like it had found nothing
     * when it had simply asked the wrong question. The old Function Library
     * stated the same rule on its own filter bar.
     */
    private function throughPosition(Builder $query, ?int $positionId = null, ?\Closure $constrain = null): Builder
    {
        return $query->where(function (Builder $outer) use ($positionId, $constrain): void {
            $outer->whereNull('position_id')
                ->orWhere(function (Builder $scoped) use ($positionId, $constrain): void {
                    $positionId === null
                        ? $scoped->whereHas('position', $constrain)
                        : $scoped->where('position_id', $positionId);
                });
        });
    }

    public function __construct(private readonly RubricSync $rubric) {}

    public function store(StoreJobFunctionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $function = DB::transaction(function () use ($data): JobFunction {
            $function = JobFunction::create($this->attributes($data) + ['is_active' => true]);

            $this->rubric->apply($function, $data['rubric'] ?? []);

            return $function;
        });

        return redirect()->route('admin.functions.index')
            ->with('status', 'Added function "' . $this->shorten($function->title) . '".');
    }

    public function update(UpdateJobFunctionRequest $request, JobFunction $jobFunction): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $jobFunction): void {
            $jobFunction->update($this->attributes($data));

            $this->rubric->apply($jobFunction, $data['rubric'] ?? []);
        });

        return redirect()->route('admin.functions.index')
            ->with('status', 'Updated function "' . $this->shorten($jobFunction->title) . '".');
    }

    public function setActive(Request $request, JobFunction $jobFunction): RedirectResponse
    {
        // An explicit value, not a toggle: two tabs disagreeing about the
        // current state would otherwise flip it the wrong way.
        $validated = $request->validate(['active' => ['required', 'boolean']]);

        $jobFunction->update(['is_active' => $validated['active']]);

        return redirect()->route('admin.functions.index')->with(
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

        return redirect()->route('admin.functions.index')
            ->with('status', "Deleted function \"{$title}\". IPCRs already using it are untouched.");
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Keeps only the link the chosen route actually uses.
     *
     * This is what makes the hidden Alpine branches harmless: they are still
     * submitted, and whatever they carry is discarded here rather than
     * quietly handing the function to an audience nobody chose.
     */
    private function attributes(array $data): array
    {
        // Only the branch the form was on survives. The other two are still
        // submitted - a hidden field always is - and carrying a stale link
        // through would hand the function to an audience nobody chose.
        [$position, $designation] = match ($data['applies_to']) {
            'position'    => [$data['position_id'] ?? null, null],
            'designation' => [null, $data['designation_id'] ?? null],
            'everyone'    => [null, null],
        };

        return [
            'category'          => FunctionCategory::from($data['category']),
            'title'             => $data['title'],
            'success_indicator' => $data['success_indicator'] ?? null,

            // The sentence the reported figure fills in. Null clears it,
            // which is how a function goes back to being written by hand.
            'accomplishment_template' => $data['accomplishment_template'] ?? null,

            'position_id'       => $position,
            'designation_id'    => $designation,
        ];
    }

    /** Titles are free text and can run long; flash messages should not. */
    private function shorten(string $title): string
    {
        return mb_strlen($title) > 60 ? mb_substr($title, 0, 57) . '...' : $title;
    }
}
