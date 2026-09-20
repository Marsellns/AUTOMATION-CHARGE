<?php

namespace App\Console\Commands;

use App\Services\ElectricityAnomalyNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportCentralizedSubmodulesDataset extends Command
{
    protected $signature = 'dataset:import-centralized-submodules';

    protected $description = 'Import datasets for Anomali Tagihan, Bongkar Rampung Mandiri, and Listrik All';

    private const BULAN_MAP = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
        'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8, 'agst' => 8,
        'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
        'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
        'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
        'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        'august' => 8,
        'ags' => 8,
    ];

    public function handle(): int
    {
        ini_set('memory_limit', '-1');

        $this->importBongkarRampung();
        $this->importAnomaliTagihan();
        $this->importListrikAll();

        $this->info('✓ Seluruh dataset sub-modul Centralized berhasil diimpor!');

        $anomalies = DB::table('anomali_tagihan_pln')
            ->where('kenaikan_persen', '>', 50)
            ->get()
            ->map(fn ($anomaly): array => (array) $anomaly)
            ->all();

        if ($anomalies !== []) {
            $sent = app(ElectricityAnomalyNotificationService::class)->send('Centralized PLN', $anomalies);
            $this->info($sent
                ? '✓ Notifikasi kenaikan listrik >50% berhasil dikirim.'
                : '✓ Notifikasi tidak dikirim ulang karena data anomali sama.');
        }

        return self::SUCCESS;
    }

    private function importBongkarRampung(): void
    {
        $file = base_path('DATASET/03 Electricity/Centralized/Bongkar Rampung/Daftar_Site_Tidak_Aktif.xlsx');
        if (! file_exists($file)) {
            return;
        }

        $this->info('Mengimpor Bongkar Rampung Mandiri...');
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);

        DB::table('bongkar_rampung_mandiri')->truncate();

        $batch = [];
        for ($i = 2; $i < count($rows); $i++) {
            $r = $rows[$i];
            $idPelanggan = trim((string) ($r[2] ?? ''));
            $siteId = trim((string) ($r[3] ?? ''));
            $siteName = trim((string) ($r[4] ?? ''));

            if ($idPelanggan === '' && $siteId === '') {
                continue;
            }

            $batch[] = [
                'id_pelanggan' => $idPelanggan,
                'site_id' => $siteId,
                'site_name' => $siteName,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($batch)) {
            DB::table('bongkar_rampung_mandiri')->insert($batch);
            $this->info('✓ Bongkar Rampung: '.count($batch).' baris.');
        }
    }

    private function importAnomaliTagihan(): void
    {
        $baseDir = base_path('DATASET/03 Electricity/Centralized/Anomali Tagihan');
        if (! is_dir($baseDir)) {
            return;
        }

        $this->info('Mengimpor Anomali Tagihan PLN...');
        DB::table('anomali_tagihan_pln')->truncate();

        $total = 0;
        foreach (['2025', '2026'] as $year) {
            $files = glob($baseDir.'/'.$year.'/*.xlsx');
            foreach ($files as $file) {
                $spreadsheet = IOFactory::load($file);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, false, false);

                $batch = [];
                for ($i = 1; $i < count($rows); $i++) {
                    $r = $rows[$i];
                    $idPelanggan = trim((string) ($r[1] ?? ''));
                    $siteId = trim((string) ($r[2] ?? ''));
                    $siteName = trim((string) ($r[3] ?? ''));
                    $bulanStr = strtolower(trim((string) ($r[4] ?? '')));
                    $tahunRaw = trim((string) ($r[5] ?? $year));
                    $hargaPrev = (float) str_replace([',', ' '], '', (string) ($r[6] ?? 0));
                    $hargaCurr = (float) str_replace([',', ' '], '', (string) ($r[7] ?? 0));
                    $kenaikanPersen = (float) str_replace([',', ' ', '%'], '', (string) ($r[8] ?? 0));

                    if ($idPelanggan === '' && $siteId === '') {
                        continue;
                    }

                    $bulan = self::BULAN_MAP[$bulanStr] ?? (int) $bulanStr;
                    if (! $bulan) {
                        $bulan = 1;
                    }
                    $tahun = (int) $tahunRaw ?: (int) $year;
                    $selisih = $hargaCurr - $hargaPrev;

                    $batch[] = [
                        'id_pelanggan' => $idPelanggan,
                        'site_id' => $siteId,
                        'site_name' => $siteName,
                        'bulan' => $bulan,
                        'tahun' => $tahun,
                        'tagihan_sebelumnya' => $hargaPrev,
                        'tagihan_saat_ini' => $hargaCurr,
                        'selisih' => $selisih,
                        'kenaikan_persen' => $kenaikanPersen,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (! empty($batch)) {
                    DB::table('anomali_tagihan_pln')->insert($batch);
                    $total += count($batch);
                }
            }
        }
        $this->info("✓ Anomali Tagihan: {$total} baris.");
    }

    private function importListrikAll(): void
    {
        $fileCandidates = [
            base_path('DATASET/03 Electricity/Centralized/Listrik All/Simawar.xlsx'),
            base_path('DATASET/03 Electricity/Centralized/Listrik All/Data Master PLN Centralized_Flagging September 2026_Eastern Jabotabek.xlsx'),
        ];
        $file = collect($fileCandidates)->first(fn (string $candidate) => is_file($candidate));
        if ($file === null) {
            $this->error('File dataset Listrik All tidak ditemukan.');
            return;
        }

        $this->info('Mengimpor Listrik All...');
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);

        DB::table('listrik_all')->truncate();

        $headerRowIndex = null;
        foreach (array_slice($rows, 0, 3, true) as $rowIndex => $row) {
            $monthHeaderCount = 0;
            foreach ($row as $header) {
                if ($this->parseMonthHeader($header) !== null) {
                    $monthHeaderCount++;
                }
            }

            if ($monthHeaderCount > 0) {
                $headerRowIndex = $rowIndex;
                break;
            }
        }

        if ($headerRowIndex === null) {
            $this->error('Header bulan pada dataset Listrik All tidak ditemukan.');

            return;
        }

        $headers = $rows[$headerRowIndex];
        $monthColumns = [];
        foreach (array_slice($headers, 6, null, true) as $column => $header) {
            $monthHeader = $this->parseMonthHeader($header);
            if ($monthHeader === null) {
                continue;
            }

            $monthColumns[] = [
                'column' => $column,
                'month' => $monthHeader['month'],
                'year' => $monthHeader['year'],
            ];
        }

        $batches = [];
        $monthNames = [1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr', 5 => 'mei', 6 => 'jun',
            7 => 'jul', 8 => 'ags', 9 => 'sep', 10 => 'okt', 11 => 'nov', 12 => 'des'];

        for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            $idPelanggan = trim((string) ($r[1] ?? ''));
            $siteId = trim((string) ($r[2] ?? ''));
            $siteName = trim((string) ($r[3] ?? ''));
            $golTarif = trim((string) ($r[6] ?? ''));
            $unitPln = trim((string) ($r[7] ?? ''));

            if ($idPelanggan === '' && $siteId === '') {
                continue;
            }

            foreach ($monthColumns as $monthColumn) {
                $year = $monthColumn['year'];
                $key = $year.':'.$idPelanggan.':'.$siteId;
                if (! isset($batches[$key])) {
                    $batches[$key] = [
                        'id_pelanggan' => $idPelanggan,
                        'site_id' => $siteId,
                        'site_name' => $siteName,
                        'gol_tarif' => $golTarif,
                        'unit_pln' => $unitPln,
                        'tahun' => $year,
                        'jan' => 0, 'feb' => 0, 'mar' => 0, 'apr' => 0,
                        'mei' => 0, 'jun' => 0, 'jul' => 0, 'ags' => 0,
                        'sep' => 0, 'okt' => 0, 'nov' => 0, 'des' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $batches[$key][$monthNames[$monthColumn['month']]] = $this->parseAmount($r[$monthColumn['column']] ?? 0);
            }
        }

        foreach (array_chunk(array_values($batches), 1000) as $chunk) {
            DB::table('listrik_all')->insert($chunk);
        }

        $this->info('✓ Listrik All: '.count($batches).' baris.');
    }

    private function parseMonthHeader(mixed $value): ?array
    {
        $header = trim((string) $value);
        if (preg_match('/^flagging\s+(.+?)\s+(\d{4})2?$/iu', $header, $matches) !== 1
            && preg_match('/^([[:alpha:]]+)\s+(\d{4})$/u', $header, $matches) !== 1) {
            return null;
        }

        $month = self::BULAN_MAP[strtolower($matches[1])] ?? null;
        if ($month === null) {
            return null;
        }

        return ['month' => $month, 'year' => (int) $matches[2]];
    }

    private function parseAmount(mixed $value): float
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-') {
            return 0;
        }

        $value = preg_replace('/[^0-9,.\-]/u', '', $value);
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = strrpos($value, ',') > strrpos($value, '.')
                ? str_replace('.', '', str_replace(',', '.', $value))
                : str_replace(',', '', $value);
        } elseif (str_contains($value, '.')) {
            $value = preg_match('/\.\d{3}$/', $value) ? str_replace('.', '', $value) : $value;
        } elseif (str_contains($value, ',')) {
            $value = preg_match('/,\d{3}$/', $value) ? str_replace(',', '', $value) : str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : 0;
    }
}
