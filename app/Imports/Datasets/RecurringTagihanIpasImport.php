<?php

namespace App\Imports\Datasets;

use App\Support\RecurringTagihanSummary;
use App\Support\RecurringTagihanWriter;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RecurringTagihanIpasImport extends BaseDatasetImport
{
    protected function table(): string
    {
        return 'recurring_tagihan_ipas';
    }

    public function collection(Collection $rows): void
    {
        $records = [];
        foreach ($rows as $row) {
            $record = $this->mapRow($row);
            if ($record === null) {
                $this->skipped++;
            } else {
                $records[] = $record;
            }
        }
        app(RecurringTagihanWriter::class)->store($records);
        $this->inserted += count($records);
    }

    public function headingRow(): int
    {
        return 1;
    }

    protected function sourceDetails(Collection $row): array
    {
        $source = $row['source_details'] ?? null;
        $details = is_string($source) && $source !== '' ? json_decode($source, true, flags: JSON_THROW_ON_ERROR) : [];

        return array_replace($details ?? [], $row->except('source_details')->filter(fn ($value) => $value !== null && $value !== '')->all());
    }

    protected function mapRow(Collection $row): ?array
    {
        if ($row->has('jumlah_rincian') || $row->has('total_tagihan')) {
            throw ValidationException::withMessages(['dataset_file' => 'File ringkasan per site tidak dapat diimpor sebagai rincian termin. Gunakan tombol Rincian CSV atau template upload.']);
        }
        $siteCode = $this->cleanText($row['siteid'] ?? $row['site_id'] ?? $row['site_code'] ?? null);
        if ($siteCode === null) {
            return null;
        }

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
            'amount' => RecurringTagihanSummary::money($row['termin_amount'] ?? $row['amount'] ?? $row['nilai'] ?? $row['nominal'] ?? null),
            'batch_name' => $this->cleanText($row['batch_name'] ?? $row['batch'] ?? null),
        ];
    }
}
