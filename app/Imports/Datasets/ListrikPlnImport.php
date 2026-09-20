<?php

namespace App\Imports\Datasets;

use Illuminate\Support\Collection;

/**
 * Electricity Centralized — Listrik PLN.
 * File: DATASET/03 Electricity/Centralized/Listrik PLN/Data Master Export.xlsx
 */
class ListrikPlnImport extends BaseDatasetImport
{
    protected function table(): string
    {
        return 'listrik_pln';
    }

    protected function mapRow(Collection $row): ?array
    {
        $idPelanggan = $this->cleanText($row['id_pelanggan'] ?? null);
        $siteId = $this->cleanText($row['site_id'] ?? null);

        if ($idPelanggan === null && $siteId === null) {
            return null;
        }

        // Daya VA
        $daya = $row['daya_va'] ?? null;
        $dayaVa = null;
        if ($daya !== null && is_numeric(str_replace([',', '.'], '', (string)$daya))) {
            $dayaVa = (int) str_replace([',', '.'], '', (string)$daya);
        }

        // Status Aktif Site
        $statusAktif = $this->cleanText($row['status_aktif_site'] ?? null);
        if ($statusAktif !== null) {
            $statusAktif = strtolower($statusAktif) === 'tidak aktif' ? 'Tidak Aktif' : 'Aktif';
        } else {
            $statusAktif = 'Aktif';
        }

        // Phasa
        $phasa = $this->cleanText($row['phasa'] ?? null);
        if ($phasa !== null) {
            if (str_contains(strtolower($phasa), '3')) {
                $phasa = '3 Phasa';
            } elseif (str_contains(strtolower($phasa), '1')) {
                $phasa = '1 Phasa';
            }
        }

        // Status AMR
        $statusAmr = $this->cleanText($row['status_amr'] ?? null);
        if ($statusAmr !== null) {
            $statusAmr = str_contains(strtolower($statusAmr), 'tidak') ? 'Tidak Ada' : 'Ada';
        }

        // Jenis Bayar
        $jenisBayar = $this->cleanText($row['jenis_bayar'] ?? null);
        if ($jenisBayar !== null) {
            if (str_contains(strtolower($jenisBayar), 'pra')) {
                $jenisBayar = 'Prabayar';
            } elseif (str_contains(strtolower($jenisBayar), 'pasca')) {
                $jenisBayar = 'Pascabayar';
            }
        }

        return [
            'id_pelanggan'      => $idPelanggan ?? ('PLN-' . ($siteId ?? uniqid())),
            'site_id'           => $siteId ?? '-',
            'site_name'         => $this->cleanText($row['site_name'] ?? null) ?? '-',
            'alamat'            => $this->cleanText($row['alamat'] ?? null),
            'status_aktif_site' => $statusAktif,
            'nama_pelanggan'    => $this->cleanText($row['nama_pelanggan'] ?? null),
            'daya_va'           => $dayaVa,
            'phasa'             => $phasa,
            'status_amr'        => $statusAmr,
            'gol_tarif'         => $this->cleanText($row['gol_tarif'] ?? null),
            'unit_layanan_pln'  => $this->cleanText($row['unit_layanan_pln'] ?? null),
            'jenis_bayar'       => $jenisBayar,
            'tp_owner'          => $this->cleanText($row['tp_owner'] ?? null),
            'nop'               => $this->cleanText($row['nop'] ?? null),
            'update_by'         => $this->cleanText($row['update_by'] ?? null),
            'tanggal'           => $this->parseDate($row['tanggal'] ?? null),
        ];
    }
}
