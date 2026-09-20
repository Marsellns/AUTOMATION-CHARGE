<?php

namespace Database\Factories;

use App\Models\Bapss;
use Illuminate\Database\Eloquent\Factories\Factory;

class BapssFactory extends Factory
{
    protected $model = Bapss::class;

    public function definition(): array
    {
        return [
            'site_code' => fake()->regexify('[A-Z]{3}[0-9]{3}'),
            'site_name' => fake()->words(2, true),
            'tgl_bapss' => fake()->date(),
            'tgl_dismantle' => fake()->optional()->date(),
            'remark' => fake()->optional()->sentence(),
            'pdf_bapss' => 'bapss/' . fake()->uuid() . '.pdf',
            'pdf_ba_dismantle' => fake()->optional()->text(40),
            'update_by' => fake()->name(),
            'tgl_update' => fake()->dateTime(),
        ];
    }
}
