<?php

namespace Database\Factories;

use App\Models\Region;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prefix = strtoupper($this->faker->lexify('???'));

        return [
            'site_id' => $prefix . $this->faker->unique()->numerify('###'),
            'site_name' => $this->faker->optional(0.9)->company(), // 10% chance null, like real data
            'region_id' => Region::factory(),
        ];
    }

    /**
     * Assign site to a specific region.
     */
    public function forRegion(Region $region): static
    {
        return $this->state(fn () => [
            'region_id' => $region->id,
            'site_id' => $region->kode . $this->faker->unique()->numerify('###'),
        ]);
    }
}
