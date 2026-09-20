<?php

namespace Database\Factories;

use App\Models\SewaLahanRenewal;
use Illuminate\Database\Eloquent\Factories\Factory;

class SewaLahanRenewalFactory extends Factory
{
    protected $model = SewaLahanRenewal::class;

    public function definition(): array
    {
        return [
            'site_code' => fake()->regexify('[A-Z]{3}[0-9]{3}'),
            'site_name' => fake()->words(2, true),
            'tahun_renewal' => fake()->year(),
            'status_dokumen' => fake()->randomElement(SewaLahanRenewal::STATUS_DOKUMEN),
            'status_perpanjangan' => fake()->randomElement(['PAID', 'SAP', 'Pending PKS', 'PKS']),
            'no_pks_baru' => 'PKS. ' . fake()->numberBetween(100, 9999) . '/RG.04/LG.05/' . fake()->year(),
            'start_date_baru' => fake()->date(),
            'end_date_baru' => fake()->date('Y-m-d', '+5 years'),
            'harga_baru' => fake()->numberBetween(10, 90) * 1_000_000,
            'total_harga_baru' => fake()->numberBetween(100, 900) * 1_000_000,
            'no_bak_baru' => fake()->optional()->text(30),
            'tgl_bak_baru' => fake()->optional()->date(),
            'no_sip' => fake()->optional()->text(30),
            'tgl_terima_sip' => fake()->optional()->date(),
            'penawaran_1' => fake()->optional()->numberBetween(1, 99) * 1_000_000,
            'nego_1' => fake()->optional()->numberBetween(1, 99) * 1_000_000,
            'penawaran_2' => fake()->optional()->numberBetween(1, 99) * 1_000_000,
            'nego_2' => fake()->optional()->numberBetween(1, 99) * 1_000_000,
            'penawaran_3' => fake()->optional()->numberBetween(1, 99) * 1_000_000,
            'nego_3' => fake()->optional()->numberBetween(1, 99) * 1_000_000,
            'no_pks_lama' => fake()->optional()->text(30),
            'start_date_lama' => fake()->optional()->date(),
            'end_date_lama' => fake()->optional()->date(),
            'harga_lama' => fake()->optional()->numberBetween(10, 90) * 1_000_000,
            'keterangan' => fake()->optional()->sentence(),
            'update_by' => fake()->name(),
            'tgl_update' => fake()->date(),
        ];
    }
}
