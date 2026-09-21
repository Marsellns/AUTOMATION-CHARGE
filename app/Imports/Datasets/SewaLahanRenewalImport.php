<?php

namespace App\Imports\Datasets;

use Illuminate\Support\Collection;

/**
 * Infrastruktur management — 01 Sewa Lahan (Renewal Eastern Jabotabek).
 * File: DATASET/02 Infrastruktur management/01 Sewa Lahan/DATABASE SIMAWAR RENEWAL.xlsx
 * Kolom "No" dan "Aksi" (artefak UI) tidak diimpor.
 */
class SewaLahanRenewalImport extends BaseDatasetImport
{
    public function headingRow(): int
    {
        return 1;
    }

    protected function table(): string
    {
        return 'sewa_lahan_renewals';
    }

    protected function mapRow(Collection $row): ?array
    {
        $siteCode = $this->cleanText($row['site_id'] ?? null);

        if ($siteCode === null) {
            return null;
        }

        return [
            'site_code'           => $siteCode,
            'site_name'           => $this->cleanText($row['site_name'] ?? null),
            'tahun_renewal'       => $this->parseYear($row['year'] ?? $row['tahun_renewal'] ?? null),
            // STATUS and STATUS DOKUMEN are different dimensions.  Do not
            // place On Air/Off Air values into the document-status column.
            'status_dokumen'      => $this->cleanText($row['status_dokumen'] ?? null),
            'status_perpanjangan' => $this->cleanText($row['status_perpanjangan'] ?? null),
            'no_pks_baru'         => $this->cleanText($row['nomor_pks_baru'] ?? $row['no_pks_baru'] ?? null),
            'start_date_baru'     => $this->parseDate($row['new_period_awal'] ?? $row['start_date_baru'] ?? null),
            'end_date_baru'       => $this->parseDate($row['new_period_akhir'] ?? $row['end_date_baru'] ?? null),
            'harga_baru'          => $this->parseMoney($row['harga_baruth'] ?? $row['harga_baru_th'] ?? $row['harga_baru'] ?? null),
            'total_harga_baru'    => $this->parseMoney($row['total_harga_baru'] ?? null),
            'no_bak_baru'         => $this->cleanText($row['no_bak_baru'] ?? null),
            'tgl_bak_baru'        => $this->parseDate($row['tanggal_bak'] ?? $row['tgl_bak_baru'] ?? null),
            'no_sip'              => $this->cleanText($row['no_sip'] ?? null),
            'tgl_terima_sip'      => $this->parseDate($row['tgl_terima_sip'] ?? null),
            'penawaran_1'         => $this->parseMoney($row['penawaran_1'] ?? null),
            'nego_1'              => $this->parseMoney($row['nego_1'] ?? null),
            'penawaran_2'         => $this->parseMoney($row['penawaran_2'] ?? null),
            'nego_2'              => $this->parseMoney($row['nego_2'] ?? null),
            'penawaran_3'         => $this->parseMoney($row['penawaran_3'] ?? null),
            'nego_3'              => $this->parseMoney($row['nego_3'] ?? null),
            'no_pks_lama'         => $this->cleanText($row['nomor_pks_existing'] ?? $row['no_pks_lama'] ?? null),
            'start_date_lama'     => $this->parseDate($row['periode_awal_existing'] ?? $row['start_date_lama'] ?? null),
            'end_date_lama'       => $this->parseDate($row['periode_akhir_existing'] ?? $row['end_date_lama'] ?? null),
            'harga_lama'          => $this->parseMoney($row['harga_existing_th'] ?? $row['harga_lama'] ?? null),
            'keterangan'          => $this->cleanText($row['keterangan'] ?? null),
            'update_by'           => $this->cleanText($row['update_by'] ?? null),
            'tgl_update'          => $this->parseDate($row['tgl_update'] ?? null),
            'source_details'      => json_encode($this->sourceDetails($row), JSON_UNESCAPED_UNICODE),
        ];
    }
}
