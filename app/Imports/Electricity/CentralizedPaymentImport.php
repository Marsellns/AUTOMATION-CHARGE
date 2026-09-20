<?php

namespace App\Imports\Electricity;

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

            $batch[] = [
                'id_pelanggan' => $id,
                'site_id' => $siteId,
                'site_name' => $this->text($r[3] ?? null),
                'daya' => $this->integer($r[4] ?? null),
                'phasa' => $this->text($r[5] ?? null),
                'gol_tarif' => $this->text($r[6] ?? null),
                'unit_pln' => $this->text($r[7] ?? null),
                'harga' => $this->number($r[8] ?? 0),
                'update_by' => $this->text($r[9] ?? null) ?: 'System Import',
                'tanggal_status' => $this->date($r[10] ?? null) ?: sprintf('%04d-%02d-15', $this->tahun, $this->bulan),
                'status' => $this->status,
                'bulan' => $this->bulan,
                'tahun' => $this->tahun,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($batch, 500) as $chunk) {
            DB::table('payment_pln')->insert($chunk);
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

    private function number(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $value = preg_replace('/[^0-9,.-]/', '', (string) $value);
        if (str_contains($value, '.') && str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, '.')) {
            $value = preg_match('/\.\d{3}$/', $value) ? str_replace('.', '', $value) : $value;
        } else {
            $value = str_replace(',', '.', $value);
        }
        return is_numeric($value) ? (float) $value : 0;
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
