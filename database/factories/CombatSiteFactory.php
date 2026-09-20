<?php

namespace Database\Factories;

use App\Models\CombatSite;
use Illuminate\Database\Eloquent\Factories\Factory;

class CombatSiteFactory extends Factory
{
    protected $model = CombatSite::class;

    public function definition(): array
    {
        return [
            'site_code' => 'COC' . fake()->numberBetween(10, 999),
            'site_name' => fake()->words(2, true),
            'tahun_justi_dirnet' => fake()->year(),
            'status_dokumen' => fake()->randomElement(CombatSite::STATUS_DOKUMEN),
            'status_perpanjangan' => fake()->randomElement(['Paid', 'Pending PKS', 'PKS']),
            'no_pks_baru' => 'PKS.' . fake()->numberBetween(100, 9999) . '/RG.04/LG.05/' . fake()->year(),
            'start_date_baru' => fake()->date(),
            'end_date_baru' => fake()->date('Y-m-d', '+1 year'),
            'harga_baru' => fake()->numberBetween(10, 30) * 1_000_000,
            'total_harga_baru' => fake()->numberBetween(10, 30) * 1_000_000,
            'penawaran_1' => fake()->optional()->numberBetween(1, 30) * 1_000_000,
            'nego_1' => fake()->optional()->numberBetween(1, 30) * 1_000_000,
            'penawaran_2' => fake()->optional()->numberBetween(1, 30) * 1_000_000,
            'nego_2' => fake()->optional()->numberBetween(1, 30) * 1_000_000,
            'penawaran_3' => fake()->optional()->numberBetween(1, 30) * 1_000_000,
            'nego_3' => fake()->optional()->numberBetween(1, 30) * 1_000_000,
            'no_pks_lama' => fake()->optional()->text(30),
            'start_date_lama' => fake()->optional()->date(),
            'end_date_lama' => fake()->optional()->date(),
            'harga_lama' => fake()->optional()->numberBetween(10, 30) * 1_000_000,
            'nomor_surat' => fake()->optional()->text(30),
            'keterangan' => fake()->optional()->sentence(),
            'update_by' => fake()->name(),
            'tanggal' => fake()->date(),
        ];
    }
}
