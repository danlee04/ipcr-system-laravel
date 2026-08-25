<?php

namespace App\Support;

use App\Enums\FunctionCategory;
use App\Models\JobFunction;
use Illuminate\Support\Collection;

/**
 * Ang resulta ng FunctionCatalogService::availableFor() - nakahiwalay
 * kada kategorya para direktang magamit sa isang tabbed/grouped na
 * picker UI kapag nag-a-add ang empleyado ng functions sa IPCR niya.
 *
 * Tandaan: ito ay LISTAHAN NG PWEDENG PILIIN lang. Walang laman dito
 * na awtomatikong isasama sa IPCR - manual pa rin lahat ng pag-add.
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
