<?php

namespace App\Imports\Datasets;

use Illuminate\Support\Collection;

class RecurringTagihanIpasImport extends BaseDatasetImport
{
    protected function table(): string { return 'recurring_tagihan_ipas'; }

    public function headingRow(): int
    {
        return 1;
    }

    protected function mapRow(Collection $row): ?array
    {
        $siteCode = $this->cleanText($row['siteid'] ?? $row['site_id'] ?? $row['site_code'] ?? null);
        if ($siteCode === null) return null;

        return [
            'source_details' => json_encode($this->sourceDetails($row), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'site_code' => $siteCode,
            'site_name' => $this->cleanText($row['site_name'] ?? null),
            'tp' => $this->cleanText($row['tp'] ?? $row['tower_provider'] ?? null),
            'contract_type' => $this->cleanText($row['contract_type'] ?? null),
            'termin' => $this->cleanText($row['termin'] ?? null),
            'periode_ke' => $this->cleanText($row['periode_ke'] ?? $row['periode'] ?? null),
            'termin_start' => $this->parseDate($row['termin_start'] ?? $row['start_date'] ?? null),
            'termin_end' => $this->parseDate($row['termin_end'] ?? $row['end_date'] ?? null),
            'amount' => $this->parseMoney($row['termin_amount'] ?? $row['amount'] ?? $row['nilai'] ?? $row['nominal'] ?? null),
            'batch_name' => $this->cleanText($row['batch_name'] ?? $row['batch'] ?? null),
        ];
    }
}
