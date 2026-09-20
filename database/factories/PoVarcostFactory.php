<?php

namespace Database\Factories;

use App\Models\PoVarcost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PoVarcost>
 */
class PoVarcostFactory extends Factory
{
    protected $model = PoVarcost::class;

    public function definition(): array
    {
        return [
            'po_number' => 'PO-' . fake()->unique()->numerify('########'),
            'expense_type' => fake()->randomElement(PoVarcost::EXPENSE_TYPES),
            'description' => fake()->sentence(),
            'gr_status' => fake()->randomElement(['Open', 'Partial', 'Complete']),
            'po_year' => fake()->numberBetween(2024, 2026),
            'delivery_date' => fake()->date(),
            'update_by' => fake()->name(),
            'update_at' => now(),
        ];
    }
}
