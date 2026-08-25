<?php

namespace App\Services;

use App\Enums\FunctionCategory;
use App\Models\Employee;
use App\Models\JobFunction;
use App\Support\EmployeeFunctionCatalog;
use Illuminate\Support\Collection;

/**
 * Sinasagot ng service na ito ang tanong: "Anong mga functions ang
 * PWEDENG piliin ng empleyadong ito kapag gumagawa siya ng IPCR?"
 *
 * Mahalagang tandaan: PANUKALA lang ang listahang ibinabalik dito.
 * Hindi ito otomatikong idinaragdag sa IPCR - ang employee/HR pa rin
 * ang mano-manong pipili at magdadagdag ng gustong item bilang
 * ipcr_items row.
 *
 * Saan galing ang bawat kategorya:
 *   core      -> ang IISANG plantilla position ng empleyado
 *   strategic -> lahat ng KASALUKUYANG ACTIVE designations niya
 *   support   -> lahat ng KASALUKUYANG ACTIVE designations niya
 *   common    -> ang open pool, bukas sa lahat
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

    /** Mula sa iisang plantilla position ng empleyado. */
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
     * Mula sa LAHAT ng kasalukuyang active designations ng empleyado.
     * Dito ang aggregation na ginagawa para kay Mary Jane - kapag may
     * OIC-Budget AT OIC-HRMO siyang parehong active, pareho silang
     * lalabas dito.
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

    /** Ang open pool - walang kabit na position o designation, bukas sa lahat. */
    private function commonFunctions(): Collection
    {
        return JobFunction::query()
            ->active()
            ->common()
            ->orderBy('title')
            ->get();
    }
}
