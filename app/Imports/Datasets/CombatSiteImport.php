<?php

namespace App\Imports\Datasets;

use Illuminate\Support\Collection;

/**
 * Infrastruktur management — 02 Combat.
 * File: DATASET/02 Infrastruktur management/02 Combat/NEW DATABASE COMBAT SIMAWAR.xlsx
 * (sheets DATABASE + DATABASE_REVENUE; both are intentionally imported into
 * the snapshot because the workbook is the current source of truth.)
 *
 * Satu site bisa muncul beberapa kali (per tahun justi dirnet), sehingga
 * tidak ada unique constraint — tabel snapshot diisi ulang tiap import.
 * Blok bulanan Jan–Jun 2026 disimpan flat; kolom PnL di sumber berbentuk
 * "1.543.712 (Profit)" / "-17.485.329 (Loss)" → parseMoney menangani.
 */
class CombatSiteImport extends BaseDatasetImport
{
    public function headingRow(): int
    {
        return 1;
    }

    private const MONTHS = ['jan', 'feb', 'mar', 'apr', 'mei', 'jun'];

    protected function table(): string
    {
        return 'combat_sites';
    }

    protected function mapRow(Collection $row): ?array
    {
        $siteCode = $this->cleanText($row['site_id'] ?? null);

        if ($siteCode === null) {
            return null;
        }

        $mapped = [
            'site_code'           => $siteCode,
            'site_name'           => $this->cleanText($row['site_name'] ?? null),
            'tahun_justi_dirnet'  => $this->parseYear($row['tahun_justi_dirnet'] ?? $row['renewal_cycle'] ?? null),
            'status_dokumen'      => $this->cleanText($row['status_dokumen'] ?? $row['status'] ?? null),
            'status_perpanjangan' => $this->cleanText($row['status_perpanjangan'] ?? null),
            'no_pks_baru'         => $this->cleanText($row['nomor_pks_baru'] ?? $row['no_pks_baru'] ?? null),
            'start_date_baru'     => $this->parseDate($row['periode_awal_baru'] ?? $row['start_date_baru'] ?? null),
            'end_date_baru'       => $this->parseDate($row['periode_akhir_baru'] ?? $row['end_date_baru'] ?? null),
            'harga_baru'          => $this->parseMoney($row['total_harga_baru'] ?? $row['harga_baru'] ?? null),
            'total_harga_baru'    => $this->parseMoney($row['total_harga_baru'] ?? null),
            'penawaran_1'         => $this->parseMoney($row['penawaran_1'] ?? null),
            'nego_1'              => $this->parseMoney($row['nego_1'] ?? null),
            'penawaran_2'         => $this->parseMoney($row['penawaran_2'] ?? null),
            'nego_2'              => $this->parseMoney($row['nego_2'] ?? null),
            'penawaran_3'         => $this->parseMoney($row['penawaran_3'] ?? null),
            'nego_3'              => $this->parseMoney($row['nego_3'] ?? null),
            'no_pks_lama'         => $this->cleanText($row['nomor_pks_existing'] ?? $row['no_pks_lama'] ?? null),
            'start_date_lama'     => $this->parseDate($row['periode_awal_existing'] ?? $row['start_date_lama'] ?? null),
            'end_date_lama'       => $this->parseDate($row['periode_akhir_existing'] ?? $row['end_date_lama'] ?? null),
            'harga_lama'          => $this->parseMoney($row['total_harga_existing'] ?? $row['harga_lama'] ?? null),
            'nomor_surat'         => $this->cleanText($row['nomor_surat'] ?? null),
            'keterangan'          => $this->cleanText($row['keterangan'] ?? null),
            'update_by'           => $this->cleanText($row['update_by'] ?? null),
            'tanggal'             => $this->parseDate($row['tanggal'] ?? null),
        ];

        // Blok bulanan: heading "Revenue Jan 2026" → "revenue_jan_2026", dst.
        foreach (self::MONTHS as $month) {
            $mapped["revenue_{$month}_2026"] = $this->parseMoney($row["revenue_{$month}_2026"] ?? null);
            $mapped["cost_{$month}_2026"] = $this->parseMoney($row["cost_{$month}_2026"] ?? null);
            $mapped["pnl_{$month}_2026"] = $this->parseMoney($row["pnl_{$month}_2026"] ?? null);
        }

        if ($mapped['revenue_mei_2026'] === null && isset($row['revenue_mei_2026'])) {
            $mapped['revenue_mei_2026'] = $this->parseMoney($row['revenue_mei_2026']);
            $margin = $this->parseMoney($row['margindirect'] ?? null);
            $mapped['pnl_mei_2026'] = $margin;
            $mapped['cost_mei_2026'] = $mapped['revenue_mei_2026'] !== null && $margin !== null
                ? $mapped['revenue_mei_2026'] - $margin
                : null;
        }

        $mapped['source_details'] = json_encode($this->sourceDetails($row), JSON_UNESCAPED_UNICODE);

        return $mapped;
    }
}
