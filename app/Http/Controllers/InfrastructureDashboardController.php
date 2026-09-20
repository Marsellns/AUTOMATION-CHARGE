<?php

namespace App\Http\Controllers;

use App\Models\CombatSite;
use App\Models\SewaLahanRenewal;
use App\Models\SiteOwner;
use App\Support\LeaseStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class InfrastructureDashboardController extends Controller
{
    public function index(): View
    {
        return view('infrastruktur.index');
    }

    public function data(\Illuminate\Http\Request $request): JsonResponse
    {
        $sewa = SewaLahanRenewal::query()->get();
        $combat = CombatSite::query()->get();
        $scope = $request->string('scope')->toString();
        $ownershipScope = $this->ownershipScope($request->input('ownership_scope'));
        $ownerRows = SiteOwner::query()->get();
        $ownersBySite = $ownerRows->keyBy(fn ($owner) => strtoupper(trim((string) $owner->site_code)));

        // Site Telkomsel dan Site TP adalah portfolio Sewa Lahan.  Terapkan
        // scope sebelum seluruh kartu, chart, peringatan, dan prioritas
        // dihitung agar angka yang terlihat selalu konsisten dengan tabelnya.
        if ($scope === 'sewa' && $ownershipScope !== null) {
            $sewa = $sewa
                ->filter(fn ($row): bool => $this->ownerBucket($row, $ownersBySite) === $ownershipScope)
                ->values();
        }

        $all = $scope === 'sewa' ? $sewa : ($scope === 'combat' ? $combat : $sewa->concat($combat));
        $uniqueBySite = static function ($rows) {
            // Combat's canonical workbook has a master sheet and a revenue
            // sheet.  Prefer the richer master row when both sheets contain
            // the same Site ID; keep the revenue-only row only for IDs that
            // exist there exclusively.
            return $rows
            ->sortByDesc(static function ($row): int {
                $details = is_array($row->source_details) ? $row->source_details : [];
                $score = (filled($row->status_dokumen) ? 16 : 0)
                    + (filled($row->status_perpanjangan) ? 8 : 0)
                    + (filled($row->end_date_baru ?? $row->end_date_lama) ? 4 : 0)
                    + (filled($details['status'] ?? null) ? 2 : 0)
                    + (filled($row->site_name) ? 1 : 0);
                return ($score * 1000000) + (int) $row->id;
            })
            ->unique(fn ($row): string => strtoupper(trim((string) $row->site_code)))
            ->values();
        };
        $sewaSites = $uniqueBySite($sewa);
        $combatSites = $uniqueBySite($combat);
        $allSites = $uniqueBySite($all);

        $isOperational = static function ($row): bool {
            $status = strtolower(trim(($row->status_dokumen ?? '').' '.($row->status_perpanjangan ?? '')));
            return !preg_match('/dismantle|off ?air|non.?operational|non.?aktif|unlock|relokasi|migrasi/', $status);
        };
        $needsAttention = static function ($row): bool {
            $status = strtolower(trim(($row->status_dokumen ?? '').' '.($row->status_perpanjangan ?? '')));
            return (bool) preg_match('/nego|pending|belum|proses|legal|perpanjang|finalisasi/', $status);
        };
        $withoutPks = static fn ($row): bool => blank($row->no_pks_baru) && blank($row->no_pks_lama);
        $riskRows = $allSites->filter(fn ($row) => $needsAttention($row) || $withoutPks($row));
        $riskValue = (float) $riskRows->sum(fn ($row) => (float) ($row->total_harga_baru ?: $row->harga_baru ?: 0));
        // Use the same normalized site lookup as the detail endpoint.  A few
        // source files contain leading/trailing spaces or different casing in
        // Site ID; filtering SiteOwner with upper-cased values would
        // make the owner chart and its drill-down disagree.
        $ownerCounts = collect(['TP' => 0, 'Telkomsel' => 0, 'Lainnya / Tidak Terpetakan' => 0]);
        foreach ($allSites as $row) {
            $bucket = $this->ownerBucket($row, $ownersBySite);
            $ownerCounts->put($bucket, $ownerCounts->get($bucket, 0) + 1);
        }

        $monthly = [];
        foreach (['jan', 'feb', 'mar', 'apr', 'mei', 'jun'] as $month) {
            $engMonth = $month === 'mei' ? 'may' : $month;
            $monthly[] = [
                'label' => ucfirst($month),
                'revenue' => (float) $allSites->sum(function ($row) use ($month, $engMonth): float {
                    $details = is_array($row->source_details) ? $row->source_details : [];
                    return (float) ($row->{"revenue_{$month}_2026"} ?? $details["rev_{$month}_26"] ?? $details["rev_{$engMonth}_26"] ?? 0);
                }),
                'cost' => (float) $allSites->sum(function ($row) use ($month, $engMonth): float {
                    $details = is_array($row->source_details) ? $row->source_details : [];
                    return (float) ($row->{"cost_{$month}_2026"} ?? $details["cost_{$month}_26"] ?? $details["cost_{$engMonth}_26"] ?? 0);
                }),
                'pnl' => (float) $allSites->sum(function ($row) use ($month, $engMonth): float {
                    $details = is_array($row->source_details) ? $row->source_details : [];
                    return (float) ($row->{"pnl_{$month}_2026"} ?? $details["pnl_{$month}_26"] ?? $details["pnl_{$engMonth}_26"] ?? 0);
                }),
            ];
        }
        $statusBreakdown = $allSites->groupBy(fn ($row) => $this->dimensionKey('status_dokumen', $row->status_dokumen))
            ->map(function ($rows): array {
                $name = $this->dimensionLabel('status_dokumen', $rows->first()?->status_dokumen);
                return ['name' => $name, 'y' => $rows->unique('site_code')->count(), 'filter_field' => 'status_dokumen', 'filter_value' => $name];
            })
            ->sortByDesc('y')->values()->take(10)->values();
        // The main dashboard labels this chart as Renewal (Sewa Lahan), so
        // do not mix Combat's Justi years into it.  On the module pages the
        // same chart remains scoped to that module and includes its own year
        // field (Renewal or Justi Dirnet).
        $renewalRows = $scope === 'all' ? $sewaSites : $allSites;
        $renewalYears = $renewalRows->groupBy(function ($row): string {
            $year = $row->tahun_renewal ?? $row->tahun_justi_dirnet;
            return $this->dimensionKey('tahun', $year);
        })
            ->map(function ($rows): array {
                $name = $this->dimensionLabel('tahun', $rows->first()?->tahun_renewal ?? $rows->first()?->tahun_justi_dirnet);
                return ['name' => $name, 'y' => $rows->unique('site_code')->count(), 'filter_field' => 'tahun', 'filter_value' => $name];
            })
            ->sortKeys()->values();
        $contractBySource = $scope === 'sewa'
            ? [['name' => 'Sewa Lahan', 'y' => (float) $sewaSites->sum(fn ($row) => (float) ($row->total_harga_baru ?: $row->harga_baru ?: 0)), 'filter_field' => 'source', 'filter_value' => 'Sewa Lahan']]
            : ($scope === 'combat'
                ? [['name' => 'Combat', 'y' => (float) $combatSites->sum(fn ($row) => (float) ($row->total_harga_baru ?: $row->harga_baru ?: 0)), 'filter_field' => 'source', 'filter_value' => 'Combat']]
                : [
                    ['name' => 'Sewa Lahan', 'y' => (float) $sewaSites->sum(fn ($row) => (float) ($row->total_harga_baru ?: $row->harga_baru ?: 0)), 'filter_field' => 'source', 'filter_value' => 'Sewa Lahan'],
                    ['name' => 'Combat', 'y' => (float) $combatSites->sum(fn ($row) => (float) ($row->total_harga_baru ?: $row->harga_baru ?: 0)), 'filter_field' => 'source', 'filter_value' => 'Combat'],
                ]);
        $pksStatus = $allSites->groupBy(fn ($row) => $this->dimensionKey('pks_status', $this->pksStatusValue($row)))
            ->map(function ($items): array {
                $name = $this->pksStatusValue($items->first());
                return ['name' => $name, 'y' => $items->unique('site_code')->count(), 'filter_field' => 'pks_status', 'filter_value' => $name];
            })
            ->sortByDesc('y')->values();
        $leaseStatus = $allSites->groupBy(fn ($row) => LeaseStatus::fromEndDate($row->end_date_baru ?? $row->end_date_lama))
            ->map(fn ($items, $name) => ['name' => $name, 'y' => $items->unique('site_code')->count(), 'filter_field' => 'status_masa_sewa', 'filter_value' => $name])
            ->sortBy(fn ($item) => array_search($item['name'], LeaseStatus::labels(), true))
            ->values();

        // The charts count unique Site ID values.  Keep the notification
        // drill-down on the same basis so duplicate/historical workbook rows
        // do not make a notification show a different number of sites.
        $uniqueRows = $uniqueBySite;
        $buildLeaseAlerts = static function ($rows, string $source): array {
            $alerts = [
                'expired' => [],
                'within_90' => [],
                'within_180' => [],
                'unknown' => [],
            ];

            foreach ($rows as $row) {
                $endDate = $row->end_date_baru ?? $row->end_date_lama;
                $status = LeaseStatus::fromEndDate($endDate);
                $key = match ($status) {
                    LeaseStatus::EXPIRED => 'expired',
                    LeaseStatus::WITHIN_90_DAYS => 'within_90',
                    LeaseStatus::WITHIN_180_DAYS => 'within_180',
                    LeaseStatus::NO_END_DATE => 'unknown',
                    default => null,
                };

                if ($key === null) {
                    continue;
                }

                $daysRemaining = $endDate === null
                    ? null
                    : today()->diffInDays($endDate->copy()->startOfDay(), false);
                $item = [
                    'site_code' => (string) $row->site_code,
                    'site_name' => (string) ($row->site_name ?? ''),
                    'source' => $source,
                    'end_date' => $endDate?->format('Y-m-d'),
                    'days_remaining' => $daysRemaining,
                    'status' => $status,
                ];

                $alerts[$key][] = $item;
                if ($key === 'within_90') {
                    // The "≤180 hari" notification is cumulative, while the
                    // chart category remains split into 90 and 180 days.
                    $alerts['within_180'][] = $item;
                }
            }

            foreach ($alerts as &$items) {
                usort($items, static function (array $a, array $b): int {
                    return (($a['days_remaining'] ?? PHP_INT_MAX) <=> ($b['days_remaining'] ?? PHP_INT_MAX));
                });
            }

            return $alerts;
        };
        $alertRows = [
            'expired' => [],
            'within_90' => [],
            'within_180' => [],
            'unknown' => [],
        ];
        $alertSources = $scope === 'sewa'
            ? [['rows' => $uniqueRows($sewa), 'source' => 'Sewa Lahan']]
            : ($scope === 'combat'
                ? [['rows' => $uniqueRows($combat), 'source' => 'Combat']]
                : [
                    ['rows' => $uniqueRows($sewa), 'source' => 'Sewa Lahan'],
                    ['rows' => $uniqueRows($combat), 'source' => 'Combat'],
                ]);
        foreach ($alertSources as $alertSource) {
            $sourceAlerts = $buildLeaseAlerts($alertSource['rows'], $alertSource['source']);
            foreach ($sourceAlerts as $key => $items) {
                $alertRows[$key] = array_merge($alertRows[$key], $items);
            }
        }
        $renewalStatus = $allSites->groupBy(fn ($row) => $this->dimensionKey('status_perpanjangan', $row->status_perpanjangan))
            ->map(function ($items): array {
                $name = $this->dimensionLabel('status_perpanjangan', $items->first()?->status_perpanjangan);
                return ['name' => $name, 'y' => $items->unique('site_code')->count(), 'filter_field' => 'status_perpanjangan', 'filter_value' => $name];
            })
            ->sortByDesc('y')->values();
        $airStatus = $allSites->groupBy(fn ($row) => $this->dimensionKey('status', $this->airStatusValue($row)))
            ->map(function ($items): array {
                $name = $this->airStatusValue($items->first());
                return ['name' => $name, 'y' => $items->unique('site_code')->count(), 'filter_field' => 'status', 'filter_value' => $name];
            })
            ->sortByDesc('y')->values();
        $nop = $allSites->groupBy(fn ($row) => $this->nopValue($row))
            ->map(fn ($items, $name) => ['name' => $name, 'y' => $items->unique('site_code')->count(), 'filter_field' => 'nop', 'filter_value' => $name])
            ->sortByDesc('y')->values();
        $vendor = $allSites->groupBy(fn ($row) => $this->dimensionKey('vendor', $this->vendorValue($row)))
            ->map(function ($items): array {
                $name = $this->vendorValue($items->first());
                return ['name' => $name, 'y' => $items->unique('site_code')->count(), 'filter_field' => 'vendor', 'filter_value' => $name];
            })
            ->sortByDesc('y')->values();
        $health = $allSites->groupBy(fn ($row) => LeaseStatus::fromEndDate($row->end_date_baru ?? $row->end_date_lama))
            ->map(fn ($items, $name) => ['name' => $name, 'y' => $items->unique('site_code')->count(), 'filter_field' => 'health', 'filter_value' => $name])
            ->sortBy(fn ($item) => array_search($item['name'], LeaseStatus::labels(), true))->values();
        $pipeline = $allSites->groupBy(fn ($row) => $this->pipelineBucket($row))
            ->map(fn ($items, $name) => ['name' => $name, 'y' => $items->unique('site_code')->count(), 'filter_field' => 'pipeline', 'filter_value' => $name])
            ->sortByDesc('y')->values();
        $aging = $allSites->groupBy(fn ($row) => $this->pipelineBucket($row))
            ->map(function ($items, $name): array {
                $values = $items->map(function ($row): ?float {
                    $details = is_array($row->source_details) ? $row->source_details : [];
                    $value = $details['process_aging'] ?? $details['aging'] ?? $details['aging_hari'] ?? null;
                    return is_numeric($value) ? (float) $value : null;
                })->filter(fn ($value) => $value !== null && $value >= 0);
                return ['name' => $name, 'y' => $values->count() ? (int) round($values->avg()) : 0, 'count' => $items->unique('site_code')->count(), 'filter_field' => 'pipeline', 'filter_value' => $name];
            })->filter(fn ($item) => $item['count'] > 0)->sortByDesc('y')->values();
        $geography = $allSites->groupBy(fn ($row) => $this->dimensionKey('geography', $this->geographyBucket($row)))
            ->map(function ($items): array {
                $name = $this->geographyBucket($items->first());
                return ['name' => $name, 'y' => $items->unique('site_code')->count(), 'filter_field' => 'geography', 'filter_value' => $name];
            })
            ->reject(fn ($item) => $this->dimensionKey('geography', $item['name']) === $this->dimensionKey('geography', 'Tidak Diisi'))
            ->sortByDesc('y')->values()->take(12)->values();
        $priority = $allSites->filter(fn ($row) => $needsAttention($row) || $withoutPks($row) || LeaseStatus::fromEndDate($row->end_date_baru ?? $row->end_date_lama) !== LeaseStatus::SAFE)
            ->map(function ($row): array {
                $endDate = $row->end_date_baru ?? $row->end_date_lama;
                $status = LeaseStatus::fromEndDate($endDate);
                return [
                    'site_code' => (string) $row->site_code,
                    'site_name' => (string) ($row->site_name ?? ''),
                    'status' => $status,
                    'pipeline' => $this->pipelineBucket($row),
                    'filter_field' => 'site_code',
                    'filter_value' => (string) $row->site_code,
                ];
            })->take(12)->values();

        return response()->json([
            'cards' => [
                'total' => $allSites->count(),
                'records' => $all->count(),
                'active' => $allSites->filter($isOperational)->count(),
                'contract' => $allSites->filter($needsAttention)->count(),
                'without_pks' => $allSites->filter($withoutPks)->count(),
                'off_air' => $allSites->reject($isOperational)->count(),
                'risk_value' => $riskValue,
            ],
            'status' => [
                ['name' => 'Operational / Active', 'y' => $allSites->filter($isOperational)->count(), 'filter_field' => 'summary_status', 'filter_value' => 'active'],
                ['name' => 'Perlu Perhatian (Contract)', 'y' => $allSites->filter($needsAttention)->count(), 'filter_field' => 'summary_status', 'filter_value' => 'contract'],
                ['name' => 'Tanpa PKS', 'y' => $allSites->filter($withoutPks)->count(), 'filter_field' => 'summary_status', 'filter_value' => 'without_pks'],
                ['name' => 'Off Air / Non-Operational', 'y' => $allSites->reject($isOperational)->count(), 'filter_field' => 'summary_status', 'filter_value' => 'off_air'],
            ],
            'performance' => $monthly,
            'sources' => [
                ['name' => 'Sewa Lahan', 'y' => $sewaSites->count(), 'filter_field' => 'source', 'filter_value' => 'Sewa Lahan'],
                ['name' => 'Combat', 'y' => $combatSites->count(), 'filter_field' => 'source', 'filter_value' => 'Combat'],
            ],
            'owners' => $ownerCounts->map(fn ($count, $name) => ['name' => $name, 'y' => $count, 'filter_field' => 'ownership', 'filter_value' => $name])->values(),
            'status_breakdown' => $statusBreakdown,
            'renewal_years' => $renewalYears,
            'contract_by_source' => $contractBySource,
            'pks_status' => $pksStatus,
            'lease_status' => $leaseStatus,
            'renewal_status' => $renewalStatus,
            'air_status' => $airStatus,
            'nop' => $nop,
            'vendor' => $vendor,
            'health' => $health,
            'pipeline' => $pipeline,
            'aging' => $aging,
            'geography' => $geography,
            'priority' => $priority,
            'alerts' => $alertRows,
            'dataset_rows' => [
                'sewa_lahan' => $sewa->count(),
                'combat' => $combat->count(),
                'sewa_sites' => $sewaSites->count(),
                'combat_sites' => $combatSites->count(),
                'total_sites' => $allSites->count(),
            ],
        ]);
    }

    /**
     * Row-level drill-down used by chart and notification pop-ups.
     *
     * This endpoint intentionally uses the same unique Site ID rule as the
     * dashboard: the master row wins over a revenue-only row, while records
     * from Sewa Lahan and Combat remain separate sources.
     */
    public function details(Request $request): JsonResponse
    {
        $sewa = SewaLahanRenewal::query()->get();
        $combat = CombatSite::query()->get();
        $scope = $request->string('scope')->toString();
        $records = collect();

        if ($scope !== 'combat') {
            foreach ($sewa as $row) {
                $records->push(['source' => 'Sewa Lahan', 'row' => $row]);
            }
        }
        if ($scope !== 'sewa') {
            foreach ($combat as $row) {
                $records->push(['source' => 'Combat', 'row' => $row]);
            }
        }

        $ownersBySite = SiteOwner::query()
            ->get()
            ->keyBy(fn ($owner) => strtoupper(trim((string) $owner->site_code)));
        $filterField = (string) $request->input('filter_field');
        $filterValue = (string) $request->input('filter_value');
        $ownershipScope = $this->ownershipScope($request->input('ownership_scope'));

        if ($ownershipScope !== null) {
            $records = $records->filter(
                fn (array $record): bool => $this->ownerBucket($record['row'], $ownersBySite) === $ownershipScope
            )->values();
        }

        // The combined dashboard treats a Site ID as one portfolio site,
        // while a source drill-down intentionally preserves the two modules.
        // This keeps the Total Site card and its drill-down on the same basis
        // without changing the 43 Sewa / 77 Combat source counts.
        $records = $this->uniqueDetailRecords($records, $filterField !== 'source');

        if ($filterField === 'source') {
            $records = $records->filter(fn (array $record): bool => $record['source'] === $filterValue);
        }

        $records = $records->filter(function (array $record) use ($filterField, $filterValue, $ownersBySite): bool {
            $row = $record['row'];
            $statusText = strtolower(trim(($row->status_dokumen ?? '') . ' ' . ($row->status_perpanjangan ?? '')));
            $endDate = $row->end_date_baru ?? $row->end_date_lama;
            $statusDokumen = $this->dimensionLabel('status_dokumen', $row->status_dokumen);
            $statusPerpanjangan = $this->dimensionLabel('status_perpanjangan', $row->status_perpanjangan);
            $year = $row->tahun_renewal ?? $row->tahun_justi_dirnet;
            $airStatus = $this->airStatusValue($row);

            if ($filterField === '' || $filterField === 'source') {
                return true;
            }

            return match ($filterField) {
                'site_code' => strtoupper(trim((string) $row->site_code)) === strtoupper(trim($filterValue)),
                'tahun' => $this->sameDimension('tahun', $year, $filterValue),
                'status_dokumen' => $this->sameDimension('status_dokumen', $statusDokumen, $filterValue),
                'status_perpanjangan' => $this->sameDimension('status_perpanjangan', $statusPerpanjangan, $filterValue),
                'status' => $this->sameDimension('status', $airStatus, $filterValue),
                'pks_status' => $this->sameDimension('pks_status', $this->pksStatusValue($row), $filterValue),
                'status_masa_sewa' => LeaseStatus::fromEndDate($endDate) === $filterValue,
                'health' => LeaseStatus::fromEndDate($endDate) === $filterValue,
                'lease_window' => $endDate !== null
                    && today()->startOfDay()->diffInDays($endDate->copy()->startOfDay(), false) >= 0
                    && today()->startOfDay()->diffInDays($endDate->copy()->startOfDay(), false) <= 180,
                'summary_status' => match ($filterValue) {
                    'active' => ! preg_match('/dismantle|off ?air|non.?operational|non.?aktif|unlock|relokasi|migrasi/', $statusText),
                    'contract' => (bool) preg_match('/nego|pending|belum|proses|legal|perpanjang|finalisasi/', $statusText),
                    'off_air' => (bool) preg_match('/dismantle|off ?air|non.?operational|non.?aktif|unlock|relokasi|migrasi/', $statusText),
                    'without_pks' => blank($row->no_pks_baru) && blank($row->no_pks_lama),
                    default => false,
                },
                // source_details is intentionally sparse: revenue-only rows
                // do not have every master column.  Always read optional
                // dimensions through the same fallback used by the charts.
                'nop' => $this->sameDimension('nop', $this->nopValue($row), $filterValue),
                'vendor' => $this->sameDimension('vendor', $this->vendorValue($row), $filterValue),
                'pipeline' => $this->pipelineBucket($row) === $filterValue,
                'geography' => $this->sameDimension('geography', $this->geographyBucket($row), $filterValue),
                'ownership' => $this->ownerBucket($row, $ownersBySite) === $filterValue,
                default => true,
            };
        })->values();

        $data = $records->map(function (array $record) use ($ownersBySite): array {
            $row = $record['row'];
            $details = is_array($row->source_details) ? $row->source_details : [];
            $endDate = $row->end_date_baru ?? $row->end_date_lama;
            $daysRemaining = $endDate === null
                ? null
                : today()->startOfDay()->diffInDays($endDate->copy()->startOfDay(), false);

            return [
                'id' => $row->id,
                'source' => $record['source'],
                'site_code' => $row->site_code,
                'site_name' => $row->site_name,
                'tahun' => $row->tahun_renewal ?? $row->tahun_justi_dirnet,
                'status_dokumen' => $row->status_dokumen,
                'status_perpanjangan' => $row->status_perpanjangan,
                'status_masa_sewa' => LeaseStatus::fromEndDate($endDate),
                'end_date' => $endDate?->format('Y-m-d'),
                'days_remaining' => $daysRemaining,
                'owner' => $this->ownerBucket($row, $ownersBySite),
                'nop' => $this->nopValue($row),
                'vendor' => $this->vendorValue($row),
                'no_pks_baru' => $row->no_pks_baru,
                'total_harga_baru' => $row->total_harga_baru,
                // source_details is an import snapshot.  Keep useful
                // supplementary values, but do not expose Excel formulas or
                // raw serial-date cells in the detail modal.
                'source_details' => $this->displaySourceDetails($details),
            ];
        })->values();

        return response()->json([
            'count' => $data->count(),
            'source_counts' => $data->groupBy('source')->map->count()->all(),
            'data' => $data,
        ]);
    }

    /**
     * Return a stable display value for dimensions coming from Excel.
     * Dataset rows are not guaranteed to contain every source column, and
     * Excel cells may contain inconsistent whitespace/casing.
     */
    private function dimensionLabel(string $field, mixed $value, string $fallback = 'Tidak Diisi'): string
    {
        $label = trim(preg_replace('/\s+/u', ' ', (string) ($value ?? '')) ?? '');
        if ($label === '' || $label === '-') {
            return $fallback;
        }

        if ($field !== 'nop') {
            return $label;
        }

        // Treat "BOGOR", "NOP BOGOR" and "NOP-BOGOR" as one NOP.  The
        // chart shows the canonical form and the detail endpoint accepts all
        // source variants.
        $nop = preg_replace('/^NOP[\s-]*/i', '', $label) ?? $label;
        $nop = strtoupper(trim($nop));

        return $nop === '' || $nop === '-' || $nop === strtoupper($fallback)
            ? $fallback
            : 'NOP '.$nop;
    }

    private function dimensionKey(string $field, mixed $value): string
    {
        return mb_strtolower($this->dimensionLabel($field, $value), 'UTF-8');
    }

    private function sameDimension(string $field, mixed $left, mixed $right): bool
    {
        return $this->dimensionKey($field, $left) === $this->dimensionKey($field, $right);
    }

    private function rowDetails($row): array
    {
        return is_array($row->source_details) ? $row->source_details : [];
    }

    private function nopValue($row): string
    {
        $details = $this->rowDetails($row);

        return $this->dimensionLabel('nop', $details['nop'] ?? null);
    }

    private function vendorValue($row): string
    {
        $details = $this->rowDetails($row);
        $value = filled($details['vendor'] ?? null)
            ? $details['vendor']
            : ($details['tp'] ?? null);

        return $this->dimensionLabel('vendor', $value);
    }

    private function pksStatusValue($row): string
    {
        // The imported workbook uses formulas in `pks_status` (for example,
        // `=IF(LEN(TRIM(...))`).  A formula is source metadata, not a status.
        // Derive the status from the mapped PKS columns so the chart, filter,
        // and detail modal all return a human-readable, stable value.
        return filled($row->no_pks_baru) || filled($row->no_pks_lama)
            ? 'Ada PKS'
            : 'Tanpa PKS';
    }

    private function airStatusValue($row): string
    {
        $details = $this->rowDetails($row);
        $value = filled($details['status'] ?? null)
            ? $details['status']
            : ($row->status_dokumen ?? null);

        return $this->dimensionLabel('status', $value);
    }

    /**
     * Hide raw import artefacts from the UI detail view.  The normalized model
     * columns above are the canonical values for dates, PKS, status, NOP, and
     * price; the remaining fields are supplementary information only.
     */
    private function displaySourceDetails(array $details): array
    {
        $hiddenFields = [
            'site_id', 'site_name', 'status', 'status_dokumen', 'status_perpanjangan',
            'pks_status', 'status_masa_sewa', 'status_masa_sewa2',
            'nop', 'vendor', 'tp', 'ownership',
            'nomor_pks_baru', 'nomor_pks_existing',
            'total_harga_baru', 'total_harga_existing',
            'periode_awal_baru', 'periode_akhir_baru',
            'periode_awal_existing', 'periode_akhir_existing',
        ];

        return collect($details)
            ->reject(function (mixed $value, string $key) use ($hiddenFields): bool {
                if (in_array($key, $hiddenFields, true) || $value === null || $value === '') {
                    return true;
                }

                // Formulas are currently stored literally by the source
                // workbook.  Showing them is confusing and can make a valid
                // notification look like an application error.
                if (is_string($value) && str_starts_with(ltrim($value), '=')) {
                    return true;
                }

                // Revenue/cost/PnL is already summarized in its own chart.
                // Hiding raw monthly metrics keeps alert drill-downs focused
                // on operational site information.
                if (preg_match('/^(rev|cost|pnl|revenue|margin|profit|tracy|payload)|_202[0-9]|-(25|26)|(jan|feb|mar|apr|mei|may|jun|jul|aug|agu|sep|okt|oct|nov|des|dec)/i', $key)) {
                    return true;
                }

                // The import already parses these into the canonical date
                // fields.  Suppress the raw Excel serial values in the modal.
                return (bool) preg_match('/(^|_)(tgl|tanggal|date|start|end|periode_(awal|akhir))(_|$)/i', $key);
            })
            ->all();
    }

    private function uniqueDetailRecords(Collection $records, bool $global = false): Collection
    {
        return $records
            ->sortByDesc(function (array $record): int {
                $row = $record['row'];
                $details = is_array($row->source_details) ? $row->source_details : [];
                $score = (filled($row->status_dokumen) ? 16 : 0)
                    + (filled($row->status_perpanjangan) ? 8 : 0)
                    + (filled($row->end_date_baru ?? $row->end_date_lama) ? 4 : 0)
                    + (filled($details['status'] ?? null) ? 2 : 0)
                    + (filled($row->site_name) ? 1 : 0);

                return ($score * 1000000) + (int) $row->id;
            })
            ->unique(fn (array $record): string => ($global ? '' : $record['source'].':') . strtoupper(trim((string) $record['row']->site_code)))
            ->values();
    }

    private function ownerBucket($row, Collection $ownersBySite): string
    {
        $details = is_array($row->source_details) ? $row->source_details : [];
        $owner = $ownersBySite->get(strtoupper(trim((string) $row->site_code)));
        $rawLabel = filled($details['ownership'] ?? null)
            ? $details['ownership']
            : (filled($details['tp'] ?? null) ? $details['tp'] : ($owner?->site_owner ?? ''));
        $label = strtolower(trim((string) $rawLabel));

        return str_contains($label, 'telkomsel')
            ? 'Telkomsel'
            : (str_contains($label, 'tp') || str_contains($label, 'tower') ? 'TP' : 'Lainnya / Tidak Terpetakan');
    }

    /**
     * Only the two supported portfolio scopes may narrow dashboard data.
     * The explicit whitelist prevents arbitrary request values from changing
     * the interpretation of the ownership classification.
     */
    private function ownershipScope(mixed $value): ?string
    {
        return match (mb_strtolower(trim((string) $value), 'UTF-8')) {
            'telkomsel' => 'Telkomsel',
            'tp' => 'TP',
            default => null,
        };
    }

    private function pipelineBucket($row): string
    {
        $details = is_array($row->source_details) ? $row->source_details : [];
        $raw = strtolower(trim((string) ($details['current_stage'] ?? $details['status_perpanjangan'] ?? $row->status_dokumen ?? '')));
        if ($raw === '') return 'Tidak Diisi';
        if (str_contains($raw, 'paid') || str_contains($raw, 'bayar')) return 'Paid';
        if (str_contains($raw, 'drop') || str_contains($raw, 'dismantle') || str_contains($raw, 'relokasi')) return 'Drop';
        if (str_contains($raw, 'negos')) return 'Negosiasi';
        if (str_contains($raw, 'bak')) return 'BAK';
        if (str_contains($raw, 'pending') && str_contains($raw, 'pks')) return 'Pending PKS';
        if (str_contains($raw, 'pks') || str_contains($raw, 'legal')) return 'PKS';
        if (str_contains($raw, 'budget')) return 'Budget';
        if (str_contains($raw, 'finance') || str_contains($raw, 'financ')) return 'Finance';
        return 'Lainnya';
    }

    private function geographyBucket($row): string
    {
        $details = is_array($row->source_details) ? $row->source_details : [];
        foreach (['area', 'city', 'kabupaten', 'region', 'wilayah', 'kota'] as $key) {
            if (filled($details[$key] ?? null)) return trim((string) $details[$key]);
        }
        return 'Tidak Diisi';
    }

}
