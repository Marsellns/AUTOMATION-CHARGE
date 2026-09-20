<?php

namespace App\Imports\Electricity;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class CentralizedAnomaliImport implements ToCollection
{
    public function collection(Collection $rows): void
    {
        $batch = [];
        foreach ($rows->slice(1) as $row) {
            $r = array_values($row->all());
            $id = trim((string) ($r[1] ?? ''));
            $siteId = trim((string) ($r[2] ?? ''));
            if ($id === '' && $siteId === '') {
                continue;
            }
            $previous = $this->number($r[6] ?? 0);
            $current = $this->number($r[7] ?? 0);
            $batch[] = [
                'id_pelanggan' => $id,
                'site_id' => $siteId,
                'site_name' => $this->text($r[3] ?? null),
                'bulan' => $this->month($r[4] ?? null),
                'tahun' => (int) ($r[5] ?? 0) ?: null,
                'tagihan_sebelumnya' => $previous,
                'tagihan_saat_ini' => $current,
                'selisih' => $current - $previous,
                'kenaikan_persen' => $this->number($r[8] ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($batch, 500) as $chunk) {
            DB::table('anomali_tagihan_pln')->insert($chunk);
        }
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' || $value === '-' ? null : $value;
    }

    private function month(mixed $value): ?int
    {
        $months = ['januari'=>1,'februari'=>2,'maret'=>3,'april'=>4,'mei'=>5,'juni'=>6,'juli'=>7,'agustus'=>8,'september'=>9,'oktober'=>10,'november'=>11,'desember'=>12];
        return is_numeric($value) ? (int) $value : ($months[strtolower(trim((string) $value))] ?? null);
    }

    private function number(mixed $value): float
    {
        if (is_numeric($value)) return (float) $value;
        $value = preg_replace('/[^0-9,.-]/', '', (string) $value);
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, '.')) {
            $value = preg_match('/\.\d{3}$/', $value) ? str_replace('.', '', $value) : $value;
        } else {
            $value = str_replace(',', '.', $value);
        }
        return is_numeric($value) ? (float) $value : 0;
    }
}
