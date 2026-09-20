<?php

namespace Database\Factories;

use App\Models\RecurringIpas;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringIpasFactory extends Factory
{
    protected $model = RecurringIpas::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-3 years', 'now');

        return [
            'site_code'             => strtoupper(fake()->bothify('???###')),
            'site_name'             => fake()->streetName() . ' Tower',
            'alamat'                => fake()->address(),
            'site_owner'            => fake()->company(),
            'rtp'                   => fake()->optional(0.5)->company(),
            'tgl_update'            => fake()->dateTimeBetween('-6 months', 'now'),
            'contract_description'  => fake()->sentence(8),
            'source_id'             => fake()->numerify('SRC-######'),
            'sow_detail'            => fake()->sentence(6),
            'sow_id'                => fake()->numerify('SOW-######'),
            'year_amount'           => fake()->randomFloat(2, 5_000_000, 100_000_000),
            'start_date'            => $startDate,
            'end_date'              => fake()->dateTimeBetween($startDate, '+5 years'),
        ];
    }
}
