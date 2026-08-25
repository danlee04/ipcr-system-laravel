<?php

namespace Database\Factories;

use App\Enums\IpcrStatus;
use App\Models\Employee;
use App\Models\Ipcr;
use App\Models\IpcrPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ipcr>
 */
class IpcrFactory extends Factory
{
    protected $model = Ipcr::class;

    public function definition(): array
    {
        return [
            'ipcr_period_id' => IpcrPeriod::factory(),
            'employee_id'    => Employee::factory(),
            'status'         => IpcrStatus::Draft,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status'       => IpcrStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}
