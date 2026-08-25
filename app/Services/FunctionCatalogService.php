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
 *   core      -> the employee's SINGLE plantilla position
 *   strategic -> all of their CURRENTLY ACTIVE designations
 *   support   -> all of their CURRENTLY ACTIVE designations
 *   common    -> the open pool, available to everyone
 */
class FunctionCatalogService
{
    public function availableFor(Employee $employee): EmployeeFunctionCatalog
    {
        return new EmployeeFunctionCatalog(
            core: $this->coreFunctions($employee),
            strategic: $this->designationFunctions($employee, FunctionCategory::Strategic),
            support: $this->designationFunctions($employee, FunctionCategory::Support),
            common: $this->commonFunctions(),
        );
    }

    /** From the employee's single plantilla position. */
    private function coreFunctions(Employee $employee): Collection
    {
        if ($employee->position_id === null) {
            return collect();
        }

        return JobFunction::query()
            ->active()
            ->ofCategory(FunctionCategory::Core)
            ->where('position_id', $employee->position_id)
            ->orderBy('title')
            ->get();
    }

    /**
     * From ALL of the employee's currently active designations.
     * This is where they are aggregated: someone holding both OIC-Budget and
     * OIC-HRMO at the same time sees the functions from both.
     */
    private function designationFunctions(Employee $employee, FunctionCategory $category): Collection
    {
        $activeDesignationIds = $employee->activeDesignations()->pluck('designations.id');

        if ($activeDesignationIds->isEmpty()) {
            return collect();
        }

        return JobFunction::query()
            ->active()
            ->ofCategory($category)
            ->whereIn('designation_id', $activeDesignationIds)
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
