<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'employee_number'      => 'DTRC-' . $this->faker->unique()->numerify('####'),
            'first_name'           => $this->faker->firstName(),
            'last_name'            => $this->faker->lastName(),
            'employment_status'    => 'permanent',
            'is_active'            => true,
            'is_chief_of_hospital' => false,
        ];
    }

    public function chiefOfHospital(): static
    {
        return $this->state(fn (): array => ['is_chief_of_hospital' => true]);
    }
}
