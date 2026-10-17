<?php

namespace App\Imports\Electricity;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class CentralizedBongkarRampungImport implements ToCollection
{
    public function collection(Collection $rows): void
    {
        $batch = [];
        foreach ($rows->slice(2) as $row) {
            $r = array_values($row->all());
            $id = trim((string) ($r[2] ?? ''));
            $siteId = trim((string) ($r[3] ?? ''));
            if ($id === '' && $siteId === '') continue;
            $batch[] = [
                'id_pelanggan' => $id,
                'site_id' => $siteId,
                'site_name' => trim((string) ($r[4] ?? '')) ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($batch === []) throw new \RuntimeException('File Bongkar Rampung tidak berisi data.');
        foreach ($batch as $record) {
            $key = array_intersect_key($record, array_flip(['id_pelanggan', 'site_id']));
            DB::table('bongkar_rampung_mandiri')->updateOrInsert($key, static function (bool $exists) use ($record): array {
                if ($exists) unset($record['created_at']);
                return $record;
            });
        }
    }
}
