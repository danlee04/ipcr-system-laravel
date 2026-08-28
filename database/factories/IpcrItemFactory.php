<?php

namespace Database\Factories;

use App\Enums\FunctionCategory;
use App\Models\Ipcr;
use App\Models\IpcrItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IpcrItem>
 */
class IpcrItemFactory extends Factory
{
    protected $model = IpcrItem::class;

    public function definition(): array
    {
        return [
            'ipcr_id'           => Ipcr::factory(),
            'category'          => FunctionCategory::Core,
            'output'            => $this->faker->sentence(),
            'success_indicator' => $this->faker->sentence(),
            'weight'            => 10,
            'sort_order'        => 1,
        ];
    }

    public function accomplished(): static
    {
        return $this->state(fn (): array => [
            'actual_accomplishment' => $this->faker->sentence(),
        ]);
    }

    /**
     * Marked by its owner, the way a line has to be before it can be
     * submitted. The average follows on save.
     */
    public function rated(float $mark = 4): static
    {
        return $this->state(fn (): array => [
            'quality_rating'    => $mark,
            'efficiency_rating' => $mark,
            'timeliness_rating' => $mark,
        ]);
    }
}
