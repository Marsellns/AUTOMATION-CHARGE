<?php

namespace App\Imports\Datasets;

use Illuminate\Support\Collection;

class DataAssetTowerImport extends BaseDatasetImport
{
    protected function table(): string
    {
        return 'data_asset_towers';
    }

    protected function mapRow(Collection $row): ?array
    {
        $siteId = $this->cleanText($row['site_id'] ?? null);

        if ($siteId === null) {
            return null;
        }

        return [
            'site_id' => $siteId,
            'site_name' => $this->cleanText($row['site_name'] ?? null),
            'site_company' => $this->cleanText($row['site_company'] ?? null),
            'site_type' => $this->cleanText($row['site_type'] ?? null),
            'grouping' => $this->cleanText($row['grouping'] ?? null),
            'brand' => $this->cleanText($row['brand'] ?? null),
            'part_name' => $this->cleanText($row['part_name'] ?? null),
            'owner' => $this->cleanText($row['owner'] ?? null),
            'ownership_status' => $this->cleanText($row['ownership_status'] ?? null),
            'note' => $this->cleanText($row['note'] ?? null),
            'tower_height' => $this->parseMeasurement($row['tower_height'] ?? null),
            'building_height' => $this->parseMeasurement($row['building_height'] ?? null),
            'tower_type' => $this->cleanText($row['tower_type'] ?? null),
            'update_by' => $this->cleanText($row['update_by'] ?? null),
            'tanggal_update' => $this->parseDate($row['tanggal_update'] ?? null),
        ];
    }

    private function parseMeasurement(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '' || trim((string) $value) === '-') {
            return null;
        }

        $normalized = str_replace(',', '.', (string) $value);
        preg_match('/-?\d+(?:\.\d+)?/', $normalized, $matches);

        return isset($matches[0]) ? (float) $matches[0] : null;
    }
}
