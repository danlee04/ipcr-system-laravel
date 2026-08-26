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
 *   strategic, core, support -> the employee's plantilla position, and every
 *                               designation they currently hold
 *   common                   -> the open pool, available to everyone
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
            common: $this->commonFunctions(),
        );
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

        if ($employee->position_id === null && $designationIds->isEmpty()) {
            return collect();
        }

        return JobFunction::query()
            ->active()
            ->ofCategory($category)
            ->where(function ($query) use ($employee, $designationIds): void {
                if ($employee->position_id !== null) {
                    $query->where('position_id', $employee->position_id);
                }

                if ($designationIds->isNotEmpty()) {
                    $query->orWhereIn('designation_id', $designationIds);
                }
            })
            ->orderBy('title')
            ->get();
    }

    /** The open pool - tied to no position or designation, available to all. */
    private function commonFunctions(): Collection
    {
        return JobFunction::query()
            ->active()
            ->common()
            ->orderBy('title')
            ->get();
    }
}
