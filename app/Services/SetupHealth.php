<?php

namespace App\Services;

use App\Enums\FunctionCategory;
use App\Models\Division;
use App\Models\Employee;
use App\Models\IpcrPeriod;
use App\Models\JobFunction;
use App\Models\Section;

/**
 * The reference data an IPCR needs before anyone can get through the flow.
 *
 * Each of these fails late and quietly: an employee only discovers a missing
 * section head at the moment they press Submit, and a missing Chief of
 * Hospital only stops the heads, not the rank and file. Collecting them here
 * lets the dashboard say so up front.
 *
 * @return list<array{message: string, route: string}>
 */
class SetupHealth
{
    /** @return list<array{message: string, route: string}> */
    public function problems(): array
    {
        $problems = [];

        if (! IpcrPeriod::open()->exists()) {
            $problems[] = [
                'message' => 'No rating period is open, so nobody can start an IPCR.',
                'route'   => route('admin.periods.index'),
            ];
        }

        if (! Employee::query()->active()->where('is_chief_of_hospital', true)->exists()) {
            $problems[] = [
                'message' => 'No Chief of Hospital is set. Section Heads and Division Heads cannot submit without one.',
                'route'   => route('admin.employees.index'),
            ];
        }

        $headlessDivisions = Division::query()->whereNull('division_head_employee_id')->count();

        if ($headlessDivisions > 0) {
            $problems[] = [
                'message' => $this->count($headlessDivisions, 'division', 'divisions')
                    . ' without a head. Nobody in them can submit.',
                'route' => route('admin.divisions.index'),
            ];
        }

        $headlessSections = Section::query()->whereNull('section_head_employee_id')->count();

        if ($headlessSections > 0) {
            $problems[] = [
                'message' => $this->count($headlessSections, 'section', 'sections')
                    . ' without a head. Nobody in them can submit.',
                'route' => route('admin.divisions.index'),
            ];
        }

        $unfiled = JobFunction::query()
            ->where('category', FunctionCategory::Common)
            ->whereNull('rating_category')
            ->count();

        if ($unfiled > 0) {
            $problems[] = [
                'message' => $this->count($unfiled, 'common function', 'common functions')
                    . ' with no rating category. They cannot be added to an IPCR.',
                'route' => route('admin.functions.index'),
            ];
        }

        return $problems;
    }

    private function count(int $n, string $singular, string $plural): string
    {
        return $n . ' ' . ($n === 1 ? $singular : $plural);
    }
}
