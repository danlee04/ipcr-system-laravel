<?php

namespace Database\Factories;

use App\Models\IpcrPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IpcrPeriod>
 */
class IpcrPeriodFactory extends Factory
{
    protected $model = IpcrPeriod::class;

    public function definition(): array
    {
        // The table has unique(['year', 'type']), so the year advances on each
        // build to keep consecutive periods from colliding.
        static $year = 2026;

        return [
            'name'                => 'January - June ' . $year,
            'year'                => $year++,
            'type'                => 'first_semester',
            'start_date'          => '2026-01-01',
            'end_date'            => '2026-06-30',
            'submission_deadline' => '2026-07-15',
            'status'              => 'open',
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['status' => 'closed']);
    }
}
