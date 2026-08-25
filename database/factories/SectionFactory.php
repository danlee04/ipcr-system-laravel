<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Section> */
class SectionFactory extends Factory
{
    protected $model = Section::class;

    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'name'        => $this->faker->unique()->words(2, true) . ' Section',
            'code'        => strtoupper($this->faker->unique()->lexify('????')),
            'is_active'   => true,
        ];
    }
}
