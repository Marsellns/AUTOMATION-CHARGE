<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ElectricityPaymentComparisonService
{
    private const MONTHS = ['jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'ags', 'sep', 'okt', 'nov', 'des'];
    private const LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    private const PAID = "LOWER(TRIM(COALESCE(status, ''))) IN ('done', 'paid', 'lunas', 'terbayar')";

    public function summary(int $year, ?int $month = null, ?string $nop = null, string $scope = 'all'): array
    {
        $months = $month === null ? range(1, 12) : [$month];
        $nop = $nop === null || trim($nop) === '' || strcasecmp($nop, 'all') === 0
            ? null : strtoupper(preg_replace('/^NOP[\s-]+/i', '', trim($nop)));
        $series = [];
        $notes = ['Persentase adalah site dengan data pembayaran dibagi populasi snapshot/master, bukan konfirmasi pelunasan semua rekening. Bulan tanpa sumber pembayaran ditampilkan sebagai belum tersedia.'];
        if ($scope !== 'inbuilding') {
            $series[] = $this->macro($year, $months, $nop);
        }
        if ($scope !== 'centralized' && $nop === null) {
            $series[] = $this->inbuilding($year, $months);
        } elseif ($nop !== null) {
            $notes[] = 'Filter NOP berlaku untuk Site Makro. Inbuilding tidak ditampilkan karena master Inbuilding belum menyimpan NOP.';
        }

        return [
            'year' => $year,
            'labels' => array_map(fn ($value) => self::LABELS[$value - 1].' '.$year, $months),
            'periods' => array_map(fn ($value) => ['tahun' => $year, 'bulan' => $value], $months),
            'series' => $series,
            'notes' => $notes,
        ];
    }

    private function macro(int $year, array $months, ?string $nop): array
    {
        // This snapshot is imported from the Flagging columns of Listrik All.
        // Inquiry and the independent Payment PLN transaction list are not payments here.
        $query = DB::table('payment_pln_master_monthly')->where('tahun', $year)->whereIn('bulan', $months)
            ->when($nop !== null, function ($query) use ($nop) {
                $query->whereIn(DB::raw('UPPER(TRIM(site_id))'), function ($sites) use ($nop) {
                    $sites->from('listrik_pln')->selectRaw('UPPER(TRIM(site_id))')
                        ->whereRaw("REPLACE(REPLACE(UPPER(TRIM(nop)), 'NOP ', ''), 'NOP-', '') = ?", [$nop]);
                });
            });
        $stats = (clone $query)
            ->selectRaw("bulan, COUNT(DISTINCT CASE WHEN status_aktif_site IS NULL OR LOWER(TRIM(status_aktif_site)) NOT IN ('tidak aktif', 'inactive', 'off air') THEN UPPER(TRIM(site_id)) END) as population")
            ->selectRaw("COUNT(DISTINCT CASE WHEN is_paid = 1 AND amount > 0 AND (status_aktif_site IS NULL OR LOWER(TRIM(status_aktif_site)) NOT IN ('tidak aktif', 'inactive', 'off air')) THEN UPPER(TRIM(site_id)) END) as paid_sites")
            ->selectRaw("SUM(CASE WHEN is_paid = 1 AND amount > 0 AND (status_aktif_site IS NULL OR LOWER(TRIM(status_aktif_site)) NOT IN ('tidak aktif', 'inactive', 'off air')) THEN amount ELSE 0 END) as amount")
            ->groupBy('bulan')->get()->keyBy('bulan');

        // Website uploads can also populate Listrik All without the monthly import.
        $all = DB::table('listrik_all')->where('tahun', $year)
            ->when($nop !== null, function ($query) use ($nop) {
                $query->whereIn(DB::raw('UPPER(TRIM(site_id))'), function ($sites) use ($nop) {
                    $sites->from('listrik_pln')->selectRaw('UPPER(TRIM(site_id))')
                        ->whereRaw("REPLACE(REPLACE(UPPER(TRIM(nop)), 'NOP ', ''), 'NOP-', '') = ?", [$nop]);
                });
            })->selectRaw('COUNT(DISTINCT UPPER(TRIM(site_id))) as population');
        foreach ($months as $value) {
            $column = self::MONTHS[$value - 1];
            $all->selectRaw("COUNT(DISTINCT CASE WHEN {$column} > 0 THEN UPPER(TRIM(site_id)) END) as {$column}_sites, SUM(CASE WHEN {$column} > 0 THEN {$column} ELSE 0 END) as {$column}_amount");
        }
        $all = $all->first();
        $points = [];
        foreach ($months as $value) {
            $row = $stats->get($value);
            $column = self::MONTHS[$value - 1];
            $fallbackPaid = (int) ($all->{$column.'_sites'} ?? 0);
            // No inferred zero payment month when the workbook lacks that column.
            $available = $row !== null || $fallbackPaid > 0;
            $points[] = $this->point($available, (int) ($row->paid_sites ?? $fallbackPaid),
                (int) ($row->population ?? $all->population ?? 0),
                (float) ($row->amount ?? $all->{$column.'_amount'} ?? 0));
        }
        return ['key' => 'macro', 'name' => 'Site Makro', 'source' => 'Flagging Listrik All', 'population_label' => 'Site pada snapshot Listrik All', 'points' => $points];
    }

    private function inbuilding(int $year, array $months): array
    {
        $master = DB::table('listrik_inbuilding')
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('aktif', 'active', 'on air', 'on-air')")
            ->selectRaw('DISTINCT UPPER(TRIM(site_id)) as site_id');
        $population = DB::query()->fromSub(clone $master, 'master')->count();
        $stats = DB::table('payment_ibc')->where('tahun', $year)
            ->leftJoinSub($master, 'master', fn ($join) => $join->on('master.site_id', '=', DB::raw('UPPER(TRIM(payment_ibc.site_id))')))
            ->selectRaw('bulan, COUNT(*) as records_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN '.self::PAID.' AND jumlah_tagihan > 0 AND master.site_id IS NOT NULL THEN UPPER(TRIM(payment_ibc.site_id)) END) as paid_sites')
            ->selectRaw('SUM(CASE WHEN '.self::PAID.' THEN jumlah_tagihan ELSE 0 END) as amount')
            ->selectRaw('COUNT(DISTINCT CASE WHEN '.self::PAID.' AND jumlah_tagihan > 0 AND master.site_id IS NULL THEN UPPER(TRIM(payment_ibc.site_id)) END) as unmatched_sites')
            ->groupBy('bulan')->get()->keyBy('bulan');
        $points = [];
        foreach ($months as $value) {
            $row = $stats->get($value);
            $points[] = $this->point($row !== null, (int) ($row->paid_sites ?? 0), $population,
                (float) ($row->amount ?? 0)) + ['unmatched_sites' => (int) ($row->unmatched_sites ?? 0)];
        }
        return ['key' => 'inbuilding', 'name' => 'Site Inbuilding', 'source' => 'Payment IBC berstatus Done/Paid/Lunas/Terbayar',
            'population_label' => 'Site aktif pada master Inbuilding saat ini', 'points' => $points];
    }

    private function point(bool $available, int $paid, int $population, float $amount): array
    {
        return ['available' => $available, 'paid_sites' => $available ? $paid : null,
            'population' => $population, 'percentage' => $available && $population > 0 ? round($paid * 100 / $population, 2) : null,
            'amount' => $available ? round($amount, 2) : null];
    }
}
