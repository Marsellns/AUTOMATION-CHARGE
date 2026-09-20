<?php

namespace Database\Factories;

use App\Models\JaknetContract;
use Illuminate\Database\Eloquent\Factories\Factory;

class JaknetContractFactory extends Factory
{
    protected $model = JaknetContract::class;

    public function definition(): array
    {
        $mulai = fake()->dateTimeBetween('-5 years', 'now');

        return [
            'site_code'        => strtoupper(fake()->bothify('???###')),
            'site_name'        => fake()->streetName() . ' Tower',
            'no_pks'           => 'PKS/' . fake()->numerify('####/####') . '/JAKNET',
            'tanggal_mulai'    => $mulai,
            'tanggal_berakhir' => fake()->dateTimeBetween($mulai, '+5 years'),
            'contact_person'   => fake()->name(),
            'contact_address'  => fake()->address(),
            'telp'             => fake()->phoneNumber(),
            'nilai'            => fake()->randomFloat(2, 10_000_000, 200_000_000),
            'nilai_per_tahun'  => fake()->randomFloat(2, 5_000_000, 50_000_000),
            'tahun_berakhir'   => fake()->numberBetween(2025, 2030),
            'tgl_update'       => fake()->dateTimeBetween('-6 months', 'now'),
            'is_site_unlock'   => fake()->boolean(15), // ~15% chance true
        ];
    }

    /**
     * State: mark as site unlock.
     */
    public function siteUnlock(): static
    {
        return $this->state(fn () => ['is_site_unlock' => true]);
    }
}
