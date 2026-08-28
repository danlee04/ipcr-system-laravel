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
 * Three buckets, matching the three kinds of work. There used to be a fourth
 * for the common pool, which put functions open to everyone somewhere other
 * than the category they actually belong to - and then needed a second field
 * to say where they really went.
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
     */
    public function __construct(
        public Collection $core,
        public Collection $strategic,
        public Collection $support,
    ) {}

    public function forCategory(FunctionCategory $category): Collection
    {
        return match ($category) {
            FunctionCategory::Core      => $this->core,
            FunctionCategory::Strategic => $this->strategic,
            FunctionCategory::Support   => $this->support,
        };
    }

    /**
     * Every function this employee may pick, in one collection keyed by id.
     *
     * What the picker is checked against: a form sends back a list of numbers,
     * and this is what says whether each of them is one this employee could
     * have been offered.
     *
     * @return Collection<int, JobFunction>
     */
    public function all(): Collection
    {
        return $this->core
            ->concat($this->strategic)
            ->concat($this->support)
            ->keyBy('id');
    }

    public function isEmpty(): bool
    {
        return $this->core->isEmpty()
            && $this->strategic->isEmpty()
            && $this->support->isEmpty();
    }
}
