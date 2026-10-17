<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** One account row and one precedence rule for the screen, exports, and new operational accounts. */
final class ListrikAllQuery
{
    public static function forYear(int $year): Builder
    {
        $months = ['jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'ags', 'sep', 'okt', 'nov', 'des'];
        $annual = DB::table('listrik_all')->where('tahun', $year)
            ->selectRaw('UPPER(TRIM(site_id)) AS code, UPPER(TRIM(id_pelanggan)) AS account, MAX(site_name) AS site_name, MAX(gol_tarif) AS gol_tarif, MAX(unit_pln) AS unit_pln')
            ->groupByRaw('UPPER(TRIM(site_id)),UPPER(TRIM(id_pelanggan))');
        $monthly = DB::table('payment_pln_master_monthly_effective')->where('tahun', $year)
            ->selectRaw('UPPER(TRIM(site_id)) AS code, UPPER(TRIM(id_pelanggan)) AS account, MAX(site_name) AS site_name')
            ->groupByRaw('UPPER(TRIM(site_id)),UPPER(TRIM(id_pelanggan))');
        foreach ($months as $index => $month) {
            $number = $index + 1;
            $annual->selectRaw("SUM({$month}) AS {$month}");
            $monthly->selectRaw("SUM(CASE WHEN bulan={$number} THEN COALESCE(amount,0) ELSE 0 END) AS {$month},COUNT(CASE WHEN bulan={$number} THEN 1 END) AS {$month}_count");
        }
        $keys = DB::query()->fromSub(clone $annual, 'a')->select('a.code', 'a.account')
            ->union(DB::query()->fromSub(clone $monthly, 'p')->select('p.code', 'p.account'));
        $query = DB::query()->fromSub($keys, 'k')
            ->leftJoinSub($annual, 'a', fn ($join) => $join->on('a.code', '=', 'k.code')->on('a.account', '=', 'k.account'))
            ->leftJoinSub($monthly, 'p', fn ($join) => $join->on('p.code', '=', 'k.code')->on('p.account', '=', 'k.account'))
            ->selectRaw('k.code AS site_id,k.account AS id_pelanggan,COALESCE(p.site_name,a.site_name) AS site_name,a.gol_tarif,a.unit_pln,? AS tahun', [$year]);
        foreach ($months as $month) {
            $query->selectRaw("COALESCE(CASE WHEN p.{$month}_count>0 THEN p.{$month} ELSE a.{$month} END,0) AS {$month}");
        }

        return $query->orderBy('site_id')->orderBy('id_pelanggan');
    }
}
