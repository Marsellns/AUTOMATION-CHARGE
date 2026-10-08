<?php

namespace App\Imports\Electricity;

use App\Support\ElectricityAmountParser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class CentralizedPaymentImport implements ToCollection
{
    public function __construct(
        private readonly string $status,
        private readonly int $bulan,
        private readonly int $tahun,
    ) {}

    public function collection(Collection $rows): void
    {
        $batch = [];
        foreach ($rows->slice(2) as $row) {
            $r = array_values($row->all());
            $id = trim((string) ($r[1] ?? ''));
            $siteId = trim((string) ($r[2] ?? ''));
            if ($id === '' && $siteId === '') {
                continue;
            }

            $key = strtoupper($id).'|'.strtoupper($siteId);
            $batch[$key] = [
                'id_pelanggan' => $id,
                'site_id' => $siteId,
                'site_name' => $this->text($r[3] ?? null),
                'daya' => $this->integer($r[4] ?? null),
                'phasa' => $this->text($r[5] ?? null),
                'gol_tarif' => $this->text($r[6] ?? null),
                'unit_pln' => $this->text($r[7] ?? null),
                'harga' => ElectricityAmountParser::parseOrZero($r[8] ?? 0),
                'update_by' => $this->text($r[9] ?? null) ?: 'System Import',
                'tanggal_status' => $this->date($r[10] ?? null) ?: sprintf('%04d-%02d-15', $this->tahun, $this->bulan),
                'status' => $this->status,
                'bulan' => $this->bulan,
                'tahun' => $this->tahun,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($batch === []) {
            throw new \RuntimeException('File Payment tidak memiliki baris dengan ID Pelanggan atau Site ID.');
        }

        foreach ($batch as $record) {
            $key = array_intersect_key($record, array_flip([
                'id_pelanggan', 'site_id', 'status', 'bulan', 'tahun',
            ]));
            DB::table('payment_pln')->updateOrInsert($key, static function (bool $exists) use ($record): array {
                if ($exists) {
                    unset($record['created_at']);
                }
                return $record;
            });
        }
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' || $value === '-' ? null : $value;
    }

    private function integer(mixed $value): ?int
    {
        $value = preg_replace('/[^0-9-]/', '', (string) $value);
        return $value === '' || $value === '-' ? null : (int) $value;
    }

    private function date(mixed $value): ?string
    {
        if (is_numeric($value) && $value > 20000) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        $timestamp = strtotime((string) $value);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
}
