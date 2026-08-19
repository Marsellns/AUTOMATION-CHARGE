<?php

namespace Database\Seeders;

use App\Models\PoHq;
use Illuminate\Database\Seeder;
use Spatie\Activitylog\Support\ActivityLogStatus;

class PoHqSeeder extends Seeder
{
    /**
     * Data contoh PO HQ (40 baris) agar DataTables punya isi untuk diuji.
     *
     * Ini pembangkitan data demo secara bulk (bukan input manual lewat modul),
     * jadi audit log dinonaktifkan sementara — sama seperti konvensi bulk
     * import SimawarPnL. CRUD lewat UI nanti tetap tercatat di audit trail.
     */
    public function run(): void
    {
        if (PoHq::count() > 0) {
            return;
        }

        $vendors = [
            'PT Huawei Tech Investment',
            'PT Ericsson Indonesia',
            'PT Nokia Solutions Networks',
            'PT ZTE Indonesia',
            'PT Aplikanusa Lintasarta',
            'PT Fiberhome Technologies',
            'PT Infrastruktur Telekomunikasi',
            'PT Daya Mitra Telekomunikasi',
            'PT Tower Bersama Infrastructure',
            'PT Centratama Telekomunikasi',
        ];

        $descriptions = [
            'Pengadaan perangkat RAN 4G LTE beserta instalasi dan integrasi',
            'Jasa pemeliharaan preventif perangkat transmisi microwave',
            'Pengadaan baterai lithium dan rectifier untuk site remote',
            'Rollout fiber optik akses last-mile wilayah Jabodetabek',
            'Upgrade kapasitas core network EPC regional',
            'Jasa survei dan engineering design BTS baru',
            'Pengadaan antena multiband dan feeder cable',
            'Layanan managed service network monitoring 24x7',
        ];

        $locations = [
            'Jakarta', 'Bandung', 'Surabaya', 'Medan', 'Semarang',
            'Makassar', 'Denpasar', 'Balikpapan', 'Palembang', 'Batam',
        ];

        $statuses = PoHq::STATUSES;
        $rows = [];

        for ($i = 1; $i <= 40; $i++) {
            $rows[] = [
                'po_number' => sprintf('PO/HQ/2026/%04d', $i),
                'agreement_number' => $i % 5 === 0 ? null : sprintf('AGR/TSEL/2025/%03d', intdiv($i, 2) + 10),
                'vendor_name' => $vendors[($i - 1) % count($vendors)],
                'description' => $descriptions[($i - 1) % count($descriptions)],
                'expense_type' => $i % 3 === 0 ? 'Opex' : 'Capex',
                'status' => $statuses[($i - 1) % count($statuses)],
                'location' => $locations[($i - 1) % count($locations)],
                'created_at' => now()->subDays(40 - $i),
                'updated_at' => now()->subDays(40 - $i),
            ];
        }

        // Bulk demo data: cegah 40 baris audit log tanpa causer.
        $activityLogStatus = app(ActivityLogStatus::class);
        $activityLogStatus->disable();

        try {
            foreach (array_chunk($rows, 10) as $chunk) {
                PoHq::insert($chunk);
            }
        } finally {
            $activityLogStatus->enable();
        }
    }
}
