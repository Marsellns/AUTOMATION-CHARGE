<?php

namespace Database\Factories;

use App\Models\RecurringTagihanIpas;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringTagihanIpasFactory extends Factory
{
    protected $model = RecurringTagihanIpas::class;

    public function definition(): array
    {
        return [
            'site_code' => fake()->regexify('[A-Z]{3}[0-9]{3}'),
            'site_name' => fake()->words(2, true),
            'tp' => fake()->randomElement(['TP BOGOR', 'TP SERANG', 'TO PURWAKARTA']),
            'contract_type' => fake()->randomElement(['ANT', 'Ipas']),
            'termin' => 'Termin ' . fake()->numberBetween(1, 4),
            'periode_ke' => (string) fake()->numberBetween(1, 12),
            'termin_start' => fake()->date(),
            'termin_end' => fake()->date('Y-m-d', '+3 months'),
            'amount' => fake()->numberBetween(1, 100) * 1_000_000,
            'batch_name' => 'BATCH-' . fake()->year() . '-' . fake()->numberBetween(1, 12),
        ];
    }
}
