<?php

namespace App\Exports;

use App\Models\ListrikAll;
use App\Models\PaymentPlnMasterMonthly;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ListrikAllExport implements FromCollection, WithHeadings, WithMapping, WithCustomChunkSize
{
    use Exportable;

    public function __construct(
        private readonly ?int $tahun = null
    ) {}

    public function chunkSize(): int
    {
        return 1000;
    }

    public function collection(): Collection
    {
        if ($this->tahun !== null && PaymentPlnMasterMonthly::query()
            ->where('tahun', $this->tahun)
            ->exists()) {
            return DB::table('payment_pln_master_monthly')
                ->where('tahun', $this->tahun)
                ->select([
                    'site_id',
                    DB::raw("GROUP_CONCAT(DISTINCT id_pelanggan ORDER BY id_pelanggan SEPARATOR ', ') as id_pelanggan"),
                    DB::raw('MAX(site_name) as site_name'),
                    DB::raw('MAX(tahun) as tahun'),
                    DB::raw('NULL as gol_tarif'),
                    DB::raw('NULL as unit_pln'),
                    DB::raw('SUM(CASE WHEN bulan = 1 THEN COALESCE(amount, 0) ELSE 0 END) as jan'),
                    DB::raw('SUM(CASE WHEN bulan = 2 THEN COALESCE(amount, 0) ELSE 0 END) as feb'),
                    DB::raw('SUM(CASE WHEN bulan = 3 THEN COALESCE(amount, 0) ELSE 0 END) as mar'),
                    DB::raw('SUM(CASE WHEN bulan = 4 THEN COALESCE(amount, 0) ELSE 0 END) as apr'),
                    DB::raw('SUM(CASE WHEN bulan = 5 THEN COALESCE(amount, 0) ELSE 0 END) as mei'),
                    DB::raw('SUM(CASE WHEN bulan = 6 THEN COALESCE(amount, 0) ELSE 0 END) as jun'),
                    DB::raw('SUM(CASE WHEN bulan = 7 THEN COALESCE(amount, 0) ELSE 0 END) as jul'),
                    DB::raw('SUM(CASE WHEN bulan = 8 THEN COALESCE(amount, 0) ELSE 0 END) as ags'),
                    DB::raw('SUM(CASE WHEN bulan = 9 THEN COALESCE(amount, 0) ELSE 0 END) as sep'),
                    DB::raw('SUM(CASE WHEN bulan = 10 THEN COALESCE(amount, 0) ELSE 0 END) as okt'),
                    DB::raw('SUM(CASE WHEN bulan = 11 THEN COALESCE(amount, 0) ELSE 0 END) as nov'),
                    DB::raw('SUM(CASE WHEN bulan = 12 THEN COALESCE(amount, 0) ELSE 0 END) as des'),
                ])
                ->groupBy('site_id')
                ->orderBy('site_id')
                ->get();
        }

        return ListrikAll::query()
            ->when($this->tahun !== null, fn ($query) => $query->where('tahun', $this->tahun))
            ->orderBy('id')
            ->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'ID Pelanggan',
            'Site ID',
            'Site Name',
            'Gol Tarif',
            'Unit PLN',
            'Tahun',
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'Mei',
            'Jun',
            'Jul',
            'Ags',
            'Sep',
            'Okt',
            'Nov',
            'Des',
        ];
    }

    /** @param ListrikAll $row */
    public function map($row): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $row->id_pelanggan,
            $row->site_id,
            $row->site_name,
            $row->gol_tarif,
            $row->unit_pln,
            $row->tahun,
            (float) $row->jan,
            (float) $row->feb,
            (float) $row->mar,
            (float) $row->apr,
            (float) $row->mei,
            (float) $row->jun,
            (float) $row->jul,
            (float) $row->ags,
            (float) $row->sep,
            (float) $row->okt,
            (float) $row->nov,
            (float) $row->des,
        ];
    }
}
