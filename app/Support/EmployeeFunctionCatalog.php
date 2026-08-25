<?php

namespace App\Support;

use App\Enums\FunctionCategory;
use App\Models\JobFunction;
use Illuminate\Support\Collection;

/**
 * The result of FunctionCatalogService::availableFor(), split per category
 * so it can drop straight into a tabbed or grouped picker UI when the
 * employee is adding functions to their IPCR.
 *
 * Note: this is only a LIST OF WHAT MAY BE PICKED. Nothing in it is added
 * to the IPCR automatically - every addition stays manual.
 */
final readonly class EmployeeFunctionCatalog
{
    /**
     * @param  Collection<int, JobFunction>  $core
     * @param  Collection<int, JobFunction>  $strategic
     * @param  Collection<int, JobFunction>  $support
     * @param  Collection<int, JobFunction>  $common
     */
    public function __construct(
        public Collection $core,
        public Collection $strategic,
        public Collection $support,
        public Collection $common,
    ) {}

    public function forCategory(FunctionCategory $category): Collection
    {
        return match ($category) {
            FunctionCategory::Core      => $this->core,
            FunctionCategory::Strategic => $this->strategic,
            FunctionCategory::Support   => $this->support,
            FunctionCategory::Common    => $this->common,
        };
    }

    public function isEmpty(): bool
    {
        return $this->core->isEmpty()
            && $this->strategic->isEmpty()
            && $this->support->isEmpty()
            && $this->common->isEmpty();
    }
}
