<?php

namespace App\Console\Commands;

use App\Models\AnomaliTagihanInbuilding;
use App\Models\InbuildingAll;
use App\Models\ListrikInbuilding;
use App\Models\PaymentIbc;
use App\Services\ElectricityAnomalyNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ImportInbuildingDataset extends Command
{
    protected $signature = 'dataset:import-inbuilding-dataset';
    protected $description = 'Mengimpor dataset Listrik Inbuilding, Payment IBC, Anomali, dan Inbuilding All';

    public function handle(): int
    {
        $this->info('Memulai import dataset Inbuilding...');

        // 1. Import Listrik Inbuilding
        $listrikFile = base_path('DATASET/03 Electricity/Inbuilding/Listrik Inbuilding/Data Inbuilding Export.xlsx');
        if (File::exists($listrikFile)) {
            $this->info('Mengimpor Listrik Inbuilding dari Data Inbuilding Export.xlsx...');
            $spreadsheet = IOFactory::load($listrikFile);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            ListrikInbuilding::truncate();

            $records = [];
            for ($i = 2; $i < count($rows); $i++) {
                $r = $rows[$i];
                $siteId = trim((string) ($r[2] ?? ''));
                if ($siteId === '') continue;

                $dayaRaw = trim((string) ($r[9] ?? ''));
                $daya = (int) preg_replace('/[^\d]/', '', $dayaRaw);

                $hargaKwh = $this->parseHarga($r[10] ?? 0);
                $tanggal = $this->parseTanggal($r[12] ?? null);

                $records[] = [
                    'site_id' => $siteId,
                    'site_name' => trim((string) ($r[3] ?? '')),
                    'status' => trim((string) ($r[4] ?? 'active')),
                    'nama_bm' => trim((string) ($r[5] ?? '')),
                    'no_npwp' => trim((string) ($r[6] ?? '')),
                    'alamat' => trim((string) ($r[7] ?? '')),
                    'telkomsel_tp' => trim((string) ($r[8] ?? '')),
                    'daya' => $daya ?: null,
                    'harga_per_kwh' => $hargaKwh,
                    'update_by' => trim((string) ($r[11] ?? 'admin')),
                    'tanggal' => $tanggal,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($records, 100) as $chunk) {
                ListrikInbuilding::insert($chunk);
            }
            $this->info("✓ Selesai mengimpor " . count($records) . " data Listrik Inbuilding.");
        }

        // 2. Import Payment IBC Done & Pending
        PaymentIbc::truncate();

        $bulanMap = [
            'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
            'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
            'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
        ];

        $paymentFolders = [
            'Done' => base_path('DATASET/03 Electricity/Inbuilding/Payment IBC Done'),
            'Pending' => base_path('DATASET/03 Electricity/Inbuilding/Payment IBC Pending'),
        ];

        $paymentRecords = [];

        foreach ($paymentFolders as $statusName => $baseFolder) {
            if (!File::isDirectory($baseFolder)) continue;

            $years = File::directories($baseFolder);
            foreach ($years as $yearDir) {
                $yearName = (int) basename($yearDir);
                if ($yearName < 2000) continue;

                $files = File::files($yearDir);
                foreach ($files as $f) {
                    $filename = $f->getFilename();
                    if (!str_ends_with(strtolower($filename), '.xlsx')) continue;

                    $bulanNum = 1;
                    $filenameLower = strtolower($filename);
                    foreach ($bulanMap as $bName => $bNum) {
                        if (str_contains($filenameLower, $bName)) {
                            $bulanNum = $bNum;
                            break;
                        }
                    }

                    $spreadsheet = IOFactory::load($f->getPathname());
                    $sheet = $spreadsheet->getActiveSheet();
                    $rows = $sheet->toArray();

                    for ($i = 2; $i < count($rows); $i++) {
                        $r = $rows[$i];
                        $siteId = trim((string) ($r[1] ?? ''));
                        if ($siteId === '') continue;

                        $daya = (int) preg_replace('/[^\d]/', '', (string) ($r[4] ?? ''));
                        $jumlahTagihan = $this->parseHarga($r[6] ?? 0);
                        $tglUpdate = $this->parseTanggal($r[9] ?? null);

                        $paymentRecords[] = [
                            'site_id' => $siteId,
                            'site_name' => trim((string) ($r[2] ?? '')),
                            'nama_bm' => trim((string) ($r[3] ?? '')),
                            'daya' => $daya ?: null,
                            'status' => $statusName,
                            'jumlah_tagihan' => $jumlahTagihan,
                            'invoice' => trim((string) ($r[7] ?? '')),
                            'update_by' => trim((string) ($r[8] ?? 'admin')),
                            'tanggal_update_status' => $tglUpdate,
                            'bulan' => $bulanNum,
                            'tahun' => $yearName,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }
        }

        if (!empty($paymentRecords)) {
            foreach (array_chunk($paymentRecords, 200) as $chunk) {
                PaymentIbc::insert($chunk);
            }
            $this->info("✓ Selesai mengimpor " . count($paymentRecords) . " data Payment IBC.");
        }

        // 3. Generate Inbuilding All (12 Bulan Pivot Matrix)
        $this->info('Membuat matriks Inbuilding All 12 bulan...');
        InbuildingAll::truncate();

        $allSites = ListrikInbuilding::all();
        $yearsList = [2024, 2025, 2026];
        $inbuildingAllRows = [];

        foreach ($yearsList as $yr) {
            $yearPayments = PaymentIbc::where('tahun', $yr)->get()->groupBy('site_id');

            foreach ($allSites as $site) {
                $sitePayments = $yearPayments->get($site->site_id);
                $monthValues = array_fill(1, 12, 0.0);

                if ($sitePayments) {
                    foreach ($sitePayments as $sp) {
                        $monthValues[$sp->bulan] = (float) $sp->jumlah_tagihan;
                    }
                }

                $inbuildingAllRows[] = [
                    'site_id'    => $site->site_id,
                    'site_name'  => $site->site_name,
                    'status'     => $site->status ?? 'Active',
                    'nama_bm'    => $site->nama_bm,
                    'tp_nontp'   => $site->telkomsel_tp ?? 'Telkomsel',
                    'tahun'      => $yr,
                    'jan'        => $monthValues[1],
                    'feb'        => $monthValues[2],
                    'mar'        => $monthValues[3],
                    'apr'        => $monthValues[4],
                    'mei'        => $monthValues[5],
                    'jun'        => $monthValues[6],
                    'jul'        => $monthValues[7],
                    'ags'        => $monthValues[8],
                    'sep'        => $monthValues[9],
                    'okt'        => $monthValues[10],
                    'nov'        => $monthValues[11],
                    'des'        => $monthValues[12],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($inbuildingAllRows, 150) as $chunk) {
            InbuildingAll::insert($chunk);
        }
        $this->info("✓ Selesai membuat " . count($inbuildingAllRows) . " data Inbuilding All.");

        // 4. Generate Anomali Tagihan Inbuilding
        $this->info('Mendeteksi anomali kenaikan tagihan inbuilding...');
        AnomaliTagihanInbuilding::truncate();

        $anomaliRows = [];
        $sitesGrouped = PaymentIbc::orderBy('tahun')->orderBy('bulan')->get()->groupBy('site_id');

        foreach ($sitesGrouped as $siteId => $payments) {
            $sorted = $payments->sortBy(fn ($p) => $p->tahun * 100 + $p->bulan)->values();

            for ($k = 1; $k < count($sorted); $k++) {
                $prev = $sorted[$k - 1];
                $curr = $sorted[$k];

                $tagihanPrev = (float) $prev->jumlah_tagihan;
                $tagihanCurr = (float) $curr->jumlah_tagihan;

                if ($tagihanPrev > 0 && $tagihanCurr > $tagihanPrev) {
                    $selisih = $tagihanCurr - $tagihanPrev;
                    $kenaikanPersen = ($selisih / $tagihanPrev) * 100;

                    $periodePrev = sprintf('%04d-%02d', $prev->tahun, $prev->bulan);
                    $periodeCurr = sprintf('%04d-%02d', $curr->tahun, $curr->bulan);

                    $anomaliRows[] = [
                        'site_id'            => $siteId,
                        'periode_sebelumnya' => $periodePrev,
                        'tagihan_sebelumnya' => $tagihanPrev,
                        'periode_saat_ini'   => $periodeCurr,
                        'tagihan_saat_ini'   => $tagihanCurr,
                        'selisih'            => $selisih,
                        'kenaikan_persen'    => round($kenaikanPersen, 2),
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ];
                }
            }
        }

        if (!empty($anomaliRows)) {
            foreach (array_chunk($anomaliRows, 150) as $chunk) {
                AnomaliTagihanInbuilding::insert($chunk);
            }
            $this->info("✓ Selesai mendeteksi " . count($anomaliRows) . " data Anomali Tagihan Inbuilding.");

            $alertRows = array_values(array_filter(
                $anomaliRows,
                fn (array $anomaly): bool => (float) $anomaly['kenaikan_persen'] > 50
            ));

            if ($alertRows !== []) {
                app(ElectricityAnomalyNotificationService::class)->send('Inbuilding', $alertRows);
                $this->info('✓ Notifikasi kenaikan listrik >50% berhasil dikirim.');
            }
        }

        return self::SUCCESS;
    }

    private function parseHarga($raw): float
    {
        if ($raw === null || $raw === '') return 0.0;
        if (is_numeric($raw)) return (float) $raw;

        $str = trim((string) $raw);
        $str = preg_replace('/[^0-9,\.\-]/u', '', $str);

        if ($str === '' || $str === '-' || $str === '-.' || $str === '-,') {
            return 0.0;
        }

        $negative = str_starts_with($str, '-');
        $str = ltrim($str, '-');

        if (str_contains($str, ',') && str_contains($str, '.')) {
            $lastComma = strrpos($str, ',');
            $lastDot = strrpos($str, '.');

            if ($lastComma > $lastDot) {
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } else {
                $str = str_replace(',', '', $str);
            }
        } elseif (str_contains($str, ',')) {
            $commaPos = strrpos($str, ',');
            $afterComma = substr($str, $commaPos + 1);

            if (strlen($afterComma) === 3 && preg_match('/^\d{3}$/', $afterComma)) {
                $str = str_replace(',', '', $str);
            } else {
                $str = str_replace(',', '.', $str);
            }
        } elseif (str_contains($str, '.')) {
            $dotPos = strrpos($str, '.');
            $afterDot = substr($str, $dotPos + 1);

            if (strlen($afterDot) === 3 && preg_match('/^\d{3}$/', $afterDot)) {
                $str = str_replace('.', '', $str);
            }
        }

        $normalized = $negative ? '-' . $str : $str;

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function parseTanggal($raw): ?string
    {
        if ($raw === null || $raw === '') return null;
        if (is_numeric($raw)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $raw)->format('Y-m-d');
            } catch (\Throwable) {}
        }
        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
