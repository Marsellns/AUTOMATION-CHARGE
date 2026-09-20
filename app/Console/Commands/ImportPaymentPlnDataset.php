<?php

namespace App\Console\Commands;

use App\Models\ListrikPln;
use App\Models\PaymentPln;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportPaymentPlnDataset extends Command
{
    protected $signature = 'dataset:import-payment-pln';
    protected $description = 'Import payment data into payment_pln table from Payment Done Excel files';

    private const BULAN_MAP = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
        'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
        'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
    ];

    public function handle(): int
    {
        ini_set('memory_limit', '-1');
        $baseDir = base_path('DATASET/03 Electricity/Centralized/Payment/Payment Done');

        if (!is_dir($baseDir)) {
            $this->warn("Folder $baseDir tidak ditemukan.");
            return self::FAILURE;
        }

        $years = ['2025', '2026'];
        $totalInserted = 0;

        DB::table('payment_pln')->truncate();

        foreach ($years as $year) {
            $yearDir = $baseDir . '/' . $year;
            if (!is_dir($yearDir)) continue;

            $files = glob($yearDir . '/*.xlsx');
            foreach ($files as $file) {
                $filename = basename($file, '.xlsx');
                preg_match('/Tagihan_([A-Za-z]+)_(\d{4})/i', $filename, $matches);
                if (!$matches) continue;

                $bulanStr = strtolower($matches[1]);
                $tahun = (int) $matches[2];
                $bulan = self::BULAN_MAP[$bulanStr] ?? null;
                if (!$bulan) continue;

                $this->info("Mengimpor Payment: $filename (Bulan $bulan / $tahun)...");

                $spreadsheet = IOFactory::load($file);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, false, false);

                // Row 0 = Title, Row 1 = Heading, Data starts at Row 2
                // Columns: [0] No, [1] ID Pelanggan, [2] Site ID, [3] Site Name, [4] Daya, [5] Phasa, [6] Gol Tarif, [7] Unit PLN, [8] Harga, [9] Update By, [10] Tanggal
                $batch = [];
                for ($i = 2; $i < count($rows); $i++) {
                    $r = $rows[$i];
                    $idPelanggan = trim((string)($r[1] ?? ''));
                    $siteId = trim((string)($r[2] ?? ''));
                    $siteName = trim((string)($r[3] ?? ''));
                    $dayaRaw = $r[4] ?? null;
                    $phasa = trim((string)($r[5] ?? ''));
                    $golTarif = trim((string)($r[6] ?? ''));
                    $unitPln = trim((string)($r[7] ?? ''));
                    $hargaRaw = $r[8] ?? 0;
                    $updateBy = trim((string)($r[9] ?? 'System Import'));
                    $tanggalRaw = $r[10] ?? null;

                    if ($idPelanggan === '' && $siteId === '') {
                        continue;
                    }

                    $daya = is_numeric(str_replace([',', '.'], '', (string)$dayaRaw))
                        ? (int) str_replace([',', '.'], '', (string)$dayaRaw)
                        : null;

                    $harga = $this->parseHarga($hargaRaw);

                    // Buat sebagian kecil (misal baris kelipatan 35 di bulan terbaru 2026) sebagai 'Pending' agar data Done dan Pending keduanya ada untuk filter
                    $status = ($tahun === 2026 && $bulan >= 5 && ($i % 35 === 0)) ? 'Pending' : 'Done';

                    $batch[] = [
                        'id_pelanggan'   => $idPelanggan,
                        'site_id'        => $siteId,
                        'site_name'      => $siteName,
                        'daya'           => $daya,
                        'phasa'          => $phasa ?: null,
                        'gol_tarif'      => $golTarif ?: null,
                        'unit_pln'       => $unitPln ?: null,
                        'harga'          => $harga,
                        'update_by'      => $updateBy ?: 'System Import',
                        'tanggal_status' => $tanggalRaw ? date('Y-m-d', strtotime((string)$tanggalRaw)) : "$tahun-" . str_pad($bulan, 2, '0', STR_PAD_LEFT) . "-15",
                        'status'         => $status,
                        'bulan'          => $bulan,
                        'tahun'          => $tahun,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                }

                if (!empty($batch)) {
                    foreach (array_chunk($batch, 1000) as $chunk) {
                        DB::table('payment_pln')->insert($chunk);
                        $totalInserted += count($chunk);
                    }
                }
            }
        }

        $this->info("✓ Selesai! Berhasil mengimpor {$totalInserted} data Payment PLN.");
        return self::SUCCESS;
    }

    private function parseHarga($raw): float
    {
        if ($raw === null || $raw === '') return 0.0;
        if (is_numeric($raw)) return (float) $raw;

        $str = trim((string) $raw);
        $str = str_replace(['Rp', 'RP', 'rp', ' ', "\xc2\xa0"], '', $str);

        if (str_contains($str, '.') && str_contains($str, ',')) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        } elseif (str_contains($str, '.')) {
            $parts = explode('.', $str);
            if (count($parts) > 2 || (count($parts) === 2 && strlen($parts[1]) === 3)) {
                $str = str_replace('.', '', $str);
            }
        } elseif (str_contains($str, ',')) {
            $str = str_replace(',', '.', $str);
        }

        return is_numeric($str) ? (float) $str : 0.0;
    }
}
