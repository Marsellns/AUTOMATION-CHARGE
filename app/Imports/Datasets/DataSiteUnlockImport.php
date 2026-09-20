<?php

namespace App\Imports\Datasets;

use Illuminate\Support\Collection;

/**
 * Infrastruktur management — 06 Data Site Unlock.
 *
 * File: DATASET/02 Infrastruktur management/06 Data Site Unlock/Site UnlockSimawar.xlsx
 * Heading sumber berada di baris kedua.
 */
class DataSiteUnlockImport extends BaseDatasetImport
{
    protected function table(): string
    {
        return 'data_site_unlocks';
    }

    protected function mapRow(Collection $row): ?array
    {
        $siteCode = $this->cleanText($row['site_id'] ?? null);

        if ($siteCode === null) {
            return null;
        }

        return [
            'site_code'   => $siteCode,
            'site_name'   => $this->cleanText($row['site_name'] ?? null),
            'site_class'  => $this->cleanText($row['class'] ?? null),
            'city'        => $this->cleanText($row['city'] ?? null),
            'batch'       => $this->cleanText($row['batch'] ?? null),
            'status'      => $this->cleanText($row['status'] ?? null),
            'final_status' => $this->cleanText($row['final_status'] ?? null),
            'update_by'   => $this->cleanText($row['update_by'] ?? null),
            'tanggal'     => $this->parseDate($row['tanggal'] ?? null),
        ];
    }
}
