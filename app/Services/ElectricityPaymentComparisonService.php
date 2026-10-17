<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
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
        $query = $this->macroSnapshot($year, $months, $nop);
        $stats = (clone $query)
            ->selectRaw("bulan, COUNT(DISTINCT CASE WHEN status_aktif_site IS NULL OR LOWER(TRIM(status_aktif_site)) NOT IN ('tidak aktif', 'inactive', 'off air') THEN UPPER(TRIM(site_id)) END) as population")
            ->selectRaw("COUNT(DISTINCT CASE WHEN is_paid = 1 AND amount > 0 AND (status_aktif_site IS NULL OR LOWER(TRIM(status_aktif_site)) NOT IN ('tidak aktif', 'inactive', 'off air')) THEN UPPER(TRIM(site_id)) END) as paid_sites")
            ->selectRaw("SUM(CASE WHEN is_paid = 1 AND amount > 0 AND (status_aktif_site IS NULL OR LOWER(TRIM(status_aktif_site)) NOT IN ('tidak aktif', 'inactive', 'off air')) THEN amount ELSE 0 END) as amount")
            ->groupBy('bulan')->get()->keyBy('bulan');

        // Website uploads can also populate Listrik All without the monthly import.
        $all = $this->filterNop(DB::table('listrik_all')->where('tahun', $year), $nop)
            ->selectRaw('COUNT(DISTINCT UPPER(TRIM(site_id))) as population');
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
        $master = $this->activeInbuilding();
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

    private function activeInbuilding(): Builder
    {
        return DB::table('listrik_inbuilding')
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('aktif', 'active', 'on air', 'on-air')")
            ->selectRaw('DISTINCT UPPER(TRIM(site_id)) as site_id');
    }

    private function filterNop(Builder $query, ?string $nop): Builder
    {
        return $query->when($nop !== null, function ($query) use ($nop) {
            $query->whereIn(DB::raw('UPPER(TRIM(site_id))'), function ($sites) use ($nop) {
                $sites->from('listrik_pln')->selectRaw('UPPER(TRIM(site_id))')
                    ->whereRaw("REPLACE(REPLACE(UPPER(TRIM(nop)), 'NOP ', ''), 'NOP-', '') = ?", [$nop]);
            });
        });
    }

    private function macroSnapshot(int $year, array $months, ?string $nop): Builder
    {
        return $this->filterNop(DB::table('payment_pln_master_monthly')->where('tahun', $year)->whereIn('bulan', $months), $nop);
    }

    /** Read the same payment sources as the clicked chart, grouped once per site. */
    public function details(string $source, int $year, int $month, string $metric, ?string $nop, int $page): array
    {
        $nop = $nop === null || trim($nop) === '' || strcasecmp($nop, 'all') === 0
            ? null : strtoupper(preg_replace('/^NOP[\s-]+/i', '', trim($nop)));
        abort_if($source === 'inbuilding' && $nop !== null, 422, 'Master Inbuilding belum menyimpan NOP.');
        $membership = "'Pada snapshot'";
        $siteColumn = 'site_id';
        if ($metric === 'billing') {
            $table = $source === 'macro' ? 'payment_pln' : 'payment_ibc';
            $amount = $source === 'macro' ? 'harga' : 'jumlah_tagihan';
            $query = DB::table($table)->where('tahun', $year)->where('bulan', $month);
            if ($source === 'macro') {
                $query = $this->filterNop($query, $nop);
            }
            $sourceLabel = $source === 'macro' ? 'Seluruh tagihan Payment PLN' : 'Seluruh tagihan Payment IBC';
            $membership = "'Semua status tagihan'";
        } elseif ($source === 'macro') {
            $query = $this->macroSnapshot($year, [$month], $nop);
            if ((clone $query)->exists()) {
                $query->whereRaw("(status_aktif_site IS NULL OR LOWER(TRIM(status_aktif_site)) NOT IN ('tidak aktif', 'inactive', 'off air'))")
                    ->where('is_paid', 1)->where('amount', '>', 0);
                $amount = 'amount';
            } else {
                $amount = self::MONTHS[$month - 1];
                $query = $this->filterNop(DB::table('listrik_all')->where('tahun', $year), $nop)->where($amount, '>', 0);
            }
            $sourceLabel = 'Flagging Listrik All';
        } else {
            $query = DB::table('payment_ibc')->where('tahun', $year)->where('bulan', $month)->whereRaw(self::PAID)
                ->leftJoinSub($this->activeInbuilding(), 'master', fn ($join) => $join->on('master.site_id', '=', DB::raw('UPPER(TRIM(payment_ibc.site_id))')));
            $siteColumn = 'payment_ibc.site_id';
            $amount = 'jumlah_tagihan';
            if ($metric === 'sites') {
                $query->whereNotNull('master.site_id')->where('jumlah_tagihan', '>', 0);
            }
            $membership = "CASE WHEN MAX(master.site_id) IS NULL THEN 'Di luar master aktif' ELSE 'Master aktif' END";
            $sourceLabel = 'Payment IBC berstatus Done/Paid/Lunas/Terbayar';
        }
        if ($metric === 'sites') {
            $query->whereNotNull($siteColumn);
        }
        $rows = $query->selectRaw("UPPER(TRIM({$siteColumn})) as site_id, MAX(site_name) as site_name, COUNT(*) as records_count, COALESCE(SUM({$amount}), 0) as amount, {$membership} as membership")
            ->groupByRaw("UPPER(TRIM({$siteColumn}))");
        $totals = DB::query()->fromSub(clone $rows, 'detail')->selectRaw('COUNT(*) as sites, COALESCE(SUM(amount), 0) as amount, COALESCE(SUM(records_count), 0) as records_count')->first();
        $lastPage = max(1, (int) ceil($totals->sites / 100));
        $page = min($page, $lastPage);
        $items = $rows->orderBy('site_id')->forPage($page, 100)->get();

        return [
            'source' => $sourceLabel, 'name' => $source === 'macro' ? 'Site Makro' : 'Site Inbuilding',
            'tahun' => $year, 'bulan' => $month, 'metric' => $metric,
            'note' => $metric === 'billing' ? 'Seluruh status tagihan, termasuk Pending. Nominal ini berbeda dari flagging pembayaran.'
                : ($source === 'inbuilding' && $metric === 'amount' ? 'Nominal mencakup pembayaran di luar master aktif; site tersebut tidak masuk cakupan site.' : 'Site dengan data pembayaran positif pada snapshot/master aktif. Satu baris per site; jumlah transaksi tetap ditampilkan.'),
            'total' => (int) $totals->sites, 'amount' => round((float) $totals->amount, 2), 'records_count' => (int) $totals->records_count,
            'page' => $page, 'last_page' => $lastPage, 'sites' => $items,
        ];
    }
}
