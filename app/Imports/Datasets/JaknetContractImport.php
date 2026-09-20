<?php

namespace App\Imports\Datasets;

use Illuminate\Support\Collection;

/**
 * Infrastruktur management — 05 Sewa Lahan (Jaknet & Dapot).
 * File: DATASET/Infrastruktur management/05 Sewa  Lahan ( Jaknet & Dapot)/Data Jaknet.xlsx
 * Heading sumber ALL-CAPS; "NILAI/THN" menjadi key "nilaithn".
 * Satu site punya banyak baris (histori perpanjangan kontrak).
 */
class JaknetContractImport extends BaseDatasetImport
{
    protected function table(): string
    {
        return 'jaknet_contracts';
    }

    protected function mapRow(Collection $row): ?array
    {
        $siteCode = $this->cleanText($row['site_id'] ?? null);

        if ($siteCode === null) {
            return null;
        }

        return [
            'site_code'        => $siteCode,
            'site_name'        => $this->cleanText($row['site_name'] ?? null),
            'no_pks'           => $this->cleanText($row['no_pks'] ?? null),
            'tanggal_mulai'    => $this->parseDate($row['tanggal_mulai'] ?? null),
            'tanggal_berakhir' => $this->parseDate($row['tanggal_berakhir'] ?? null),
            'contact_person'   => $this->cleanText($row['contact_person'] ?? null),
            'contact_address'  => $this->cleanText($row['contact_address'] ?? null),
            'telp'             => $this->cleanText($row['telp'] ?? null),
            'nilai'            => $this->parseMoney($row['nilai'] ?? null),
            'nilai_per_tahun'  => $this->parseMoney($row['nilaithn'] ?? null),
            'tahun_berakhir'   => $this->parseYear($row['tahun_berakhir'] ?? null),
            'tgl_update'       => $this->parseDate($row['tgl_update'] ?? null),
        ];
    }
}
