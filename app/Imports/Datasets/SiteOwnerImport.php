<?php

namespace App\Imports\Datasets;

use Illuminate\Support\Collection;

/**
 * Data Potensi — Site Owner (Dapot & ANT).
 * File: DATASET/05 Data Potensi/Site Owner (Dapot & ANT)/Simawar (2).xlsx
 */
class SiteOwnerImport extends BaseDatasetImport
{
    protected function table(): string
    {
        return 'site_owners';
    }

    public function headingRow(): int
    {
        return 2;
    }

    protected function mapRow(Collection $row): ?array
    {
        $siteCode = $this->cleanText($row['site_id'] ?? $row['site_code'] ?? null);
        $statusMla = strtoupper(str_replace('-', ' ', trim((string) ($row['status_mlanon_mla'] ?? $row['status_mla'] ?? ''))));

        if ($siteCode === null || strtolower($siteCode) === 'site id') {
            return null;
        }

        return [
            'site_code'      => $siteCode,
            'site_name'      => $this->cleanText($row['site_name'] ?? null),
            'site_class'     => $this->cleanText($row['class'] ?? $row['site_class'] ?? null),
            'alamat'         => $this->cleanText($row['address'] ?? $row['alamat'] ?? null),
            'city'           => $this->cleanText($row['city'] ?? null),
            'nop'            => $this->cleanText($row['nop'] ?? null),
            'coverage_type'  => $this->cleanText($row['coverage_type'] ?? $row['type'] ?? null),
            'status_mla'     => $statusMla === 'MLA' ? 'MLA' : ($statusMla === 'NON MLA' ? 'Non MLA' : null),
            'pln_connection' => $this->cleanText($row['pln_connection'] ?? null),
            'capacity'       => isset($row['capacity']) && is_numeric($row['capacity']) ? (int) $row['capacity'] : null,
            'id_pel'         => $this->cleanText($row['id_pel'] ?? null),
            'tower_height'   => $this->parseMoney($row['tower_height'] ?? null),
            'site_owner'     => $this->cleanText($row['site_owner'] ?? null),
            'tgl_update'     => $this->parseDate($row['tgl_update'] ?? null),
        ];
    }
}
