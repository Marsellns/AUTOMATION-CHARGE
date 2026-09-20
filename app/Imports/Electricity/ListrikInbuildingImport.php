<?php

namespace App\Imports\Electricity;

use Illuminate\Support\Collection;

class ListrikInbuildingImport extends BaseElectricityImport
{
    public function headingRow(): int
    {
        return 2;
    }

    protected function table(): string
    {
        return 'listrik_inbuilding';
    }

    protected function uniqueKey(): array
    {
        return ['site_id'];
    }

    protected function mapRow(Collection $row): ?array
    {
        $siteId = $this->cleanText($row['site_id'] ?? null);

        if ($siteId === null) {
            return null;
        }

        $status = $this->cleanText($row['status'] ?? null);
        if ($status !== null && !in_array($status, ['Aktif', 'Tidak Aktif'])) {
            $status = 'Aktif';
        }

        $telkomselTp = $this->cleanText($row['telkomsel_tp'] ?? $row['telkomsel_/_tp'] ?? $row['telkomsel / tp'] ?? null);
        if ($telkomselTp !== null && !in_array($telkomselTp, ['Telkomsel', 'TP'])) {
            $telkomselTp = null;
        }

        return [
            'site_id'         => $siteId,
            'site_name'       => $this->cleanText($row['site_name'] ?? null),
            'status'          => $status,
            'nama_bm'         => $this->cleanText($row['nama_bm'] ?? null),
            'no_npwp'         => $this->cleanText($row['no_npwp'] ?? null),
            'alamat'          => $this->cleanText($row['alamat'] ?? null),
            'telkomsel_tp'    => $telkomselTp,
            'daya'            => $this->parseInteger($row['daya'] ?? null),
            'harga_per_kwh'   => $this->parseMoney($row['harga_per_kwh'] ?? $row['harga/kwh'] ?? $row['harga_kwh'] ?? null),
            'update_by'       => $this->cleanText($row['update_by'] ?? null),
            'tanggal'         => $this->parseDate($row['tanggal'] ?? null),
        ];
    }
}