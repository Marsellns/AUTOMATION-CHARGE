<?php

namespace App\Imports\Electricity;

use Illuminate\Support\Collection;

class ListrikPlnImport extends BaseElectricityImport
{
    protected function table(): string
    {
        return 'listrik_pln';
    }

    protected function uniqueKey(): array
    {
        return ['id_pelanggan'];
    }

    protected function mapRow(Collection $row): ?array
    {
        $idPelanggan = $this->cleanText($row['id_pelanggan'] ?? null);

        if ($idPelanggan === null) {
            return null;
        }

        $statusAktifSite = $this->cleanText($row['status_aktif_site'] ?? null);
        if ($statusAktifSite !== null && !in_array($statusAktifSite, ['Aktif', 'Tidak Aktif'])) {
            $statusAktifSite = 'Aktif';
        }

        $phasa = $this->cleanText($row['phasa'] ?? null);
        if ($phasa !== null && !in_array($phasa, ['1 Phasa', '3 Phasa'])) {
            $phasa = null;
        }

        $statusAmr = $this->cleanText($row['status_amr'] ?? null);
        if ($statusAmr !== null && !in_array($statusAmr, ['Ada', 'Tidak Ada'])) {
            $statusAmr = null;
        }

        $jenisBayar = $this->cleanText($row['jenis_bayar'] ?? null);
        if ($jenisBayar !== null && !in_array($jenisBayar, ['Prabayar', 'Pascabayar'])) {
            $jenisBayar = null;
        }

        return [
            'id_pelanggan'          => $idPelanggan,
            'site_id'               => $this->cleanText($row['site_id'] ?? null),
            'site_name'             => $this->cleanText($row['site_name'] ?? null),
            'alamat'                => $this->cleanText($row['alamat'] ?? null),
            'status_aktif_site'     => $statusAktifSite,
            'nama_pelanggan'        => $this->cleanText($row['nama_pelanggan'] ?? null),
            'daya_va'               => $this->parseInteger($row['daya_va'] ?? null),
            'phasa'                 => $phasa,
            'status_amr'            => $statusAmr,
            'gol_tarif'             => $this->cleanText($row['gol_tarif'] ?? null),
            'unit_layanan_pln'      => $this->cleanText($row['unit_layanan_pln'] ?? null),
            'jenis_bayar'           => $jenisBayar,
            'tp_owner'              => $this->cleanText($row['tp_owner'] ?? null),
            'nop'                   => $this->cleanText($row['nop'] ?? null),
            'update_by'             => $this->cleanText($row['update_by'] ?? null),
            'tanggal'               => $this->parseDate($row['tanggal'] ?? null),
        ];
    }
}