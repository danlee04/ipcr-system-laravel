<?php

namespace Database\Factories;

use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Division> */
class DivisionFactory extends Factory
{
    protected $model = Division::class;

    public function definition(): array
    {
        return [
            'name'      => $this->faker->unique()->words(2, true) . ' Division',
            'code'      => strtoupper($this->faker->unique()->lexify('???')),
            'is_active' => true,
        ];
    }
}
