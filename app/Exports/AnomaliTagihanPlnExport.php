<?php

namespace App\Exports;

use App\Models\AnomaliTagihanPln;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AnomaliTagihanPlnExport implements FromQuery, WithHeadings, WithMapping, WithCustomChunkSize
{
    use Exportable;

    private const BULAN_NAMA = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function __construct(
        private readonly ?int $bulan = null,
        private readonly ?int $tahun = null,
    ) {}

    public function chunkSize(): int
    {
        return 1000;
    }

    public function query(): Builder
    {
        $query = AnomaliTagihanPln::query()
            ->where('kenaikan_persen', '>', 50)
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->orderByDesc('kenaikan_persen');

        if ($this->bulan !== null) {
            $query->where('bulan', $this->bulan);
        }

        if ($this->tahun !== null) {
            $query->where('tahun', $this->tahun);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'No',
            'ID Pelanggan',
            'Site ID',
            'Site Name',
            'Bulan',
            'Tahun',
            'Tagihan Sebelumnya (Rp)',
            'Tagihan Saat Ini (Rp)',
            'Selisih (Rp)',
            'Kenaikan (%)',
        ];
    }

    /** @param AnomaliTagihanPln $row */
    public function map($row): array
    {
        static $no = 0;
        $no++;

        $namaBulan = self::BULAN_NAMA[(int)$row->bulan] ?? (string)$row->bulan;

        return [
            $no,
            $row->id_pelanggan,
            $row->site_id,
            $row->site_name,
            $namaBulan,
            $row->tahun,
            (float) $row->tagihan_sebelumnya,
            (float) $row->tagihan_saat_ini,
            (float) $row->selisih,
            (float) $row->kenaikan_persen,
        ];
    }
}
