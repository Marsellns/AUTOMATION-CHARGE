<?php

namespace Database\Factories;

use App\Models\DataSiteUnlock;
use Illuminate\Database\Eloquent\Factories\Factory;

class DataSiteUnlockFactory extends Factory
{
    protected $model = DataSiteUnlock::class;

    public function definition(): array
    {
        return [
            'site_code'     => strtoupper(fake()->bothify('???###')),
            'site_name'     => fake()->streetName() . ' Tower',
            'site_class'    => fake()->randomElement(['Platinum', 'Gold', 'Silver', 'Bronze']),
            'city'          => fake()->city(),
            'batch'         => 'Batch ' . fake()->numberBetween(1, 10),
            'status'        => fake()->randomElement(['Open', 'In Progress', 'Closed']),
            'final_status'  => fake()->randomElement(['Approved', 'Rejected', 'Pending', null]),
            'update_by'     => fake()->name(),
            'tanggal'       => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }
}
