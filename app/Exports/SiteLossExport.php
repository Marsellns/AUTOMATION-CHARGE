<?php

namespace App\Exports;

use App\Models\SiteMonthlyMetric;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SiteLossExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(
        private readonly int $month,
        private readonly int $year,
    ) {
    }

    public function query(): Builder
    {
        return SiteMonthlyMetric::query()
            ->with('site.region')
            ->where('bulan', $this->month)
            ->where('tahun', $this->year)
            ->where('profit_loss', '<=', 0)
            ->orderBy('profit_loss');
    }

    public function headings(): array
    {
        return ['No', 'Site ID', 'Nama Site', 'Region', 'Bulan', 'Tahun', 'Revenue (Rp)', 'Cost (Rp)', 'Profit/Loss (Rp)'];
    }

    public function map($row): array
    {
        static $number = 0;
        $number++;

        return [
            $number,
            $row->site?->site_id,
            $row->site?->site_name,
            $row->site?->region?->nama,
            $this->month,
            $this->year,
            (float) $row->revenue,
            (float) $row->cost,
            (float) $row->profit_loss,
        ];
    }
}
