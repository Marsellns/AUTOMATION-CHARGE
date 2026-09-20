<?php

namespace App\Imports\Datasets;

use Illuminate\Support\Collection;

/**
 * Infrastruktur management — 04 Recurring (ANT & Ipas).
 * File: DATASET/Infrastruktur management/04 Recurring (ANT & Ipas)/Simawar (1).xlsx
 */
class RecurringIpasImport extends BaseDatasetImport
{
    protected function table(): string
    {
        return 'recurring_ipas';
    }

    protected function mapRow(Collection $row): ?array
    {
        $siteCode = $this->cleanText($row['site_id'] ?? null);

        if ($siteCode === null) {
            return null;
        }

        return [
            'site_code'            => $siteCode,
            'site_name'            => $this->cleanText($row['site_name'] ?? null),
            'alamat'               => $this->cleanText($row['alamat'] ?? null),
            'site_owner'           => $this->cleanText($row['site_owner'] ?? null),
            'rtp'                  => $this->cleanText($row['rtp'] ?? null),
            'tgl_update'           => $this->parseDate($row['tgl_update'] ?? null),
            'contract_description' => $this->cleanText($row['contract_description'] ?? null),
            'source_id'            => $this->cleanText($row['source_id'] ?? null),
            'sow_detail'           => $this->cleanText($row['sow_detail'] ?? null),
            'sow_id'               => $this->cleanText($row['sow_id'] ?? null),
            'year_amount'          => $this->parseMoney($row['year_amount'] ?? null),
            'start_date'           => $this->parseDate($row['start_date'] ?? null),
            'end_date'             => $this->parseDate($row['end_date'] ?? null),
        ];
    }
}
