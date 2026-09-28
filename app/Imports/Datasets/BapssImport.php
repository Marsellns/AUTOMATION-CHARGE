<?php

namespace App\Imports\Datasets;

use Illuminate\Support\Collection;

/**
 * Infrastruktur management — 07 BAPSS.
 * File: DATASET/Infrastruktur management/07 BAPSS/Simawar (2).xlsx
 * Kolom "No" dan "Aksi" (artefak UI) tidak diimpor.
 * tgl_dismantle bisa berisi "0000-00-00" di sumber → null.
 */
class BapssImport extends BaseDatasetImport
{
    protected function table(): string
    {
        return 'bapss';
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
            'tgl_bapss'        => $this->parseDate($row['tgl_bapss'] ?? null),
            'tgl_dismantle'    => $this->parseDate($row['tgl_dismantle'] ?? null),
            'remark'           => $this->cleanText($row['remark'] ?? null),
            // Spreadsheet tidak boleh mengatur URL atau markup yang akan
            // dirender sebagai tautan. Hanya path PDF lokal yang tervalidasi.
            'pdf_bapss'        => $this->safePdfPath($row['pdf_bapss'] ?? null),
            'pdf_ba_dismantle' => $this->safePdfPath($row['pdf_ba_dismantle'] ?? null),
            'update_by'        => $this->cleanText($row['update_by'] ?? null),
            'tgl_update'       => $this->parseDate($row['tgl_update'] ?? null, withTime: true),
        ];
    }

    private function safePdfPath(mixed $value): ?string
    {
        $path = $this->cleanText($value);
        if ($path === null) {
            return null;
        }

        $path = str_replace('\\', '/', $path);

        return preg_match('#^bapss/[A-Za-z0-9][A-Za-z0-9._/-]*\.pdf$#i', $path) === 1
            && !str_contains($path, '..')
            ? $path
            : null;
    }
}
