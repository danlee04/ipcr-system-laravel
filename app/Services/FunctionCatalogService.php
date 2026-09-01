<?php

namespace App\Services;

use App\Enums\FunctionCategory;
use App\Models\Employee;
use App\Models\JobFunction;
use App\Support\EmployeeFunctionCatalog;
use Illuminate\Support\Collection;

/**
 * This service answers one question: "Which functions MAY this employee
 * pick from when building an IPCR?"
 *
 * Worth remembering: what comes back is only a set of SUGGESTIONS. Nothing
 * here is added to the IPCR automatically - the employee or HR still picks
 * each item by hand and adds it as an ipcr_items row.
 *
 * Where each category comes from:
 *   strategic, core, support -> the employee's plantilla position, every
 *                               designation they currently hold, and every
 *                               function tied to neither
 *   elsewhere                -> every other post's work, offered separately
 *                               so that borrowing a line is deliberate
 *
 * The category is what kind of work a function is, never who sees it. Which
 * of the two links a function carries is decided per function, on the
 * Functions screen.
 */
class FunctionCatalogService
{
    public function availableFor(Employee $employee): EmployeeFunctionCatalog
    {
        return new EmployeeFunctionCatalog(
            core: $this->reaching($employee, FunctionCategory::Core),
            strategic: $this->reaching($employee, FunctionCategory::Strategic),
            support: $this->reaching($employee, FunctionCategory::Support),
            elsewhere: $this->filedElsewhere($employee),
        );
    }

    /**
     * Work the catalog files under a post that is not this employee's.
     *
     * People cover vacancies, and tasks move before the catalog catches up.
     * Refusing these outright left somebody who had done the work with no way
     * to report it, so they are offered - apart from the rest, grouped by the
     * post they came from, and never mixed into the employee's own list.
     *
     * A designation is a different matter and stays closed. That is an
     * appointment somebody holds; its functions are theirs while they hold it,
     * and borrowing one would be claiming the post.
     *
     * @return Collection<int, JobFunction>
     */
    private function filedElsewhere(Employee $employee): Collection
    {
        return JobFunction::query()
            ->active()
            // Filed under a post, and not this employee's own. A designation
            // carries no position - the two links are exclusive - so an
            // appointment never reaches this list at all.
            ->whereNotNull('job_functions.position_id')
            ->when(
                $employee->position_id !== null,
                fn ($query) => $query->where('job_functions.position_id', '!=', $employee->position_id),
            )
            ->join('positions', 'positions.id', '=', 'job_functions.position_id')
            ->orderBy('positions.title')
            ->orderBy('job_functions.title')
            ->select('job_functions.*')
            ->with('position.section.division')
            ->get();
    }

    /**
     * Every function of one category that reaches this employee.
     *
     * One method for all three rated categories, because they all reach
     * people the same two ways: through the plantilla position, or through a
     * designation currently held. The category says what kind of work it is,
     * never who sees it.
     *
     * Designations are aggregated: someone holding both OIC-Budget and
     * OIC-HRMO at once sees the functions of both.
     */
    private function reaching(Employee $employee, FunctionCategory $category): Collection
    {
        $designationIds = $employee->activeDesignations()->pluck('designations.id');

        return JobFunction::query()
            ->active()
            ->ofCategory($category)
            ->where(function ($query) use ($employee, $designationIds): void {
                // Tied to nothing: open to the whole hospital. Listed under
                // its own category rather than a pool of its own, because
                // that is the category it is rated in.
                $query->forEveryone();

                if ($employee->position_id !== null) {
                    $query->orWhere('position_id', $employee->position_id);
                }

                if ($designationIds->isNotEmpty()) {
                    $query->orWhereIn('designation_id', $designationIds);
                }
            })
            ->orderBy('title')
            ->get();
    }
}
