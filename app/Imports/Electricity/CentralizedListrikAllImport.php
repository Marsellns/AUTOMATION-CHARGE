<?php

namespace App\Imports\Electricity;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class CentralizedListrikAllImport implements ToCollection
{
    private const MONTHS = [
        'januari'=>1,'februari'=>2,'maret'=>3,'april'=>4,'mei'=>5,'juni'=>6,
        'juli'=>7,'agustus'=>8,'agst'=>8,'september'=>9,'oktober'=>10,
        'november'=>11,'desember'=>12,'jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,
        'jun'=>6,'jul'=>7,'aug'=>8,'ags'=>8,'sep'=>9,'oct'=>10,'okt'=>10,
        'nov'=>11,'dec'=>12,
    ];

    public function collection(Collection $rows): void
    {
        $rawRows = $rows->map(fn (Collection $row): array => array_values($row->all()))->all();
        $headerIndex = null;
        $monthColumns = [];

        foreach (array_slice($rawRows, 0, 3, true) as $index => $row) {
            foreach ($row as $column => $header) {
                $parsed = $this->parseMonthHeader($header);
                if ($parsed !== null) {
                    $monthColumns[] = ['column' => $column, ...$parsed];
                }
            }
            if ($monthColumns !== []) {
                $headerIndex = $index;
                break;
            }
        }

        if ($headerIndex === null) {
            throw new \RuntimeException('Header bulan pada file Listrik All tidak ditemukan.');
        }

        $monthNames = [1=>'jan',2=>'feb',3=>'mar',4=>'apr',5=>'mei',6=>'jun',7=>'jul',8=>'ags',9=>'sep',10=>'okt',11=>'nov',12=>'des'];
        $batches = [];
        foreach (array_slice($rawRows, $headerIndex + 1) as $row) {
            $id = trim((string) ($row[1] ?? ''));
            $siteId = trim((string) ($row[2] ?? ''));
            if ($id === '' && $siteId === '') continue;

            foreach ($monthColumns as $monthColumn) {
                $key = $monthColumn['year'].':'.$id.':'.$siteId;
                $batches[$key] ??= [
                    'id_pelanggan' => $id,
                    'site_id' => $siteId,
                    'site_name' => trim((string) ($row[3] ?? '')) ?: null,
                    'gol_tarif' => trim((string) ($row[6] ?? '')) ?: null,
                    'unit_pln' => trim((string) ($row[7] ?? '')) ?: null,
                    'tahun' => $monthColumn['year'],
                    'jan'=>0,'feb'=>0,'mar'=>0,'apr'=>0,'mei'=>0,'jun'=>0,
                    'jul'=>0,'ags'=>0,'sep'=>0,'okt'=>0,'nov'=>0,'des'=>0,
                    'created_at' => now(), 'updated_at' => now(),
                ];
                $batches[$key][$monthNames[$monthColumn['month']]] = $this->number($row[$monthColumn['column']] ?? 0);
            }
        }

        foreach (array_chunk(array_values($batches), 500) as $chunk) {
            DB::table('listrik_all')->insert($chunk);
        }
    }

    private function parseMonthHeader(mixed $value): ?array
    {
        $header = trim((string) $value);
        if (preg_match('/^(?:flagging\s+)?([[:alpha:]]+)\s+(\d{4})2?$/iu', $header, $matches) !== 1) {
            return null;
        }
        $month = self::MONTHS[strtolower($matches[1])] ?? null;
        return $month === null ? null : ['month' => $month, 'year' => (int) $matches[2]];
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
