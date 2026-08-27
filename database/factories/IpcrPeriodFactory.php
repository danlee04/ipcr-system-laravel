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
        /*
         * The table has unique(['year', 'type']), so each build needs its own
         * year. Taken from the table rather than a static counter: a static
         * never resets, so it climbed across the whole test run and eventually
         * walked past the 2100 the validator allows - failing a test that had
         * nothing to do with the ones that spent the numbers.
         *
         * The database is refreshed between tests, so this starts over each
         * time and only has to stay unique within one test.
         */
        $year = (int) (IpcrPeriod::max('year') ?? 2025) + 1;

        return [
            'name'                => 'January - June ' . $year,
            'year'                => $year,
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
