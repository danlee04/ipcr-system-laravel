<?php

namespace Database\Factories;

use App\Models\Designation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Designation> */
class DesignationFactory extends Factory
{
    protected $model = Designation::class;

    public function definition(): array
    {
        return [
            'title'     => 'OIC - ' . $this->faker->unique()->jobTitle(),
            'is_active' => true,
        ];
    }
}
