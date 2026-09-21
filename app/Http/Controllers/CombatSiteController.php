<?php

namespace App\Http\Controllers;

use App\Exports\CombatSiteExport;
use App\Http\Requests\UpdateCombatSiteRequest;
use App\Models\CombatSite;
use App\Models\SiteOwner;
use App\Support\InfrastructureMetrics;
use App\Support\LeaseStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CombatSiteController extends Controller
{
    /**
     * Halaman utama modul Combat: tabel DataTables server-side + modal detail saat baris diklik.
     */
    public function index(): View
    {
        $tahunList = CombatSite::query()
            ->whereNotNull('tahun_justi_dirnet')
            ->distinct()
            ->orderByDesc('tahun_justi_dirnet')
            ->pluck('tahun_justi_dirnet');

        return view('infrastruktur.combat.index', compact('tahunList'));
    }

    /**
     * Sumber data DataTables (server-side processing).
     * Mendukung filter tahun + search per kolom.
     */
    public function data(Request $request): JsonResponse
    {
        $isAdmin = auth()->user()->hasRole('admin');

        $query = CombatSite::query()->latest('id');

        // Filter tahun justi dirnet
        if ($request->filled('tahun') && $request->tahun !== 'all') {
            $query->where('tahun_justi_dirnet', (int) $request->tahun);
        }
        $this->applyDashboardFilter($query, $request);
        if ($request->boolean('unique_sites')) {
            $uniqueIds = (clone $query)
                ->reorder()
                ->select([])
                // The canonical Combat workbook stores the master sheet
                // before DATABASE_REVENUE.  MIN(id) therefore keeps the
                // richest master row for a unique Site ID.
                ->selectRaw('MIN(id)')
                ->groupBy('site_code');
            $query->whereIn('combat_sites.id', $uniqueIds);
        }

        $dataTable = DataTables::of($query);

        if ($isAdmin) {
            $dataTable->addColumn(
                'aksi',
                fn (CombatSite $c) => view('infrastruktur.combat.partials.aksi', ['combat' => $c])->render()
            );
        }

        return $dataTable
            ->addIndexColumn()
            ->addColumn('lease_duration', fn (CombatSite $c) => InfrastructureMetrics::leaseDurationLabel($c))
            ->addColumn('pks_status_label', fn (CombatSite $c) => InfrastructureMetrics::pksStatusLabel($c))
            ->addColumn('process_started_at', fn (CombatSite $c) => InfrastructureMetrics::processStartDate($c)?->toDateString())
            ->addColumn('process_aging_days', fn (CombatSite $c) => InfrastructureMetrics::processAgingDays($c))
            ->addColumn('next_action_label', fn (CombatSite $c) => InfrastructureMetrics::nextActionLabel($c))
            ->addColumn('priority_label', fn (CombatSite $c) => InfrastructureMetrics::priorityLabel($c))
            ->editColumn('status_dokumen', fn (CombatSite $c) => Str::limit($c->status_dokumen, 50))
            ->rawColumns($isAdmin ? ['aksi'] : [])
            ->toJson();
    }

    private function applyDashboardFilter($query, Request $request): void
    {
        $field = (string) $request->input('filter_field');
        $value = (string) $request->input('filter_value');
        if ($field === 'tahun') {
            $value === 'Tidak Diisi'
                ? $query->whereNull('tahun_justi_dirnet')
                : $query->where('tahun_justi_dirnet', (int) $value);
        } elseif ($field === 'summary_status') {
            $query->where(function ($subQuery) use ($value): void {
                $status = "LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(source_details, '$.status')), ''))";
                if ($value === 'active') {
                    $subQuery->whereRaw("{$status} REGEXP 'on[[:space:]]*air|active|operational'")
                        ->whereRaw("{$status} NOT REGEXP 'off[[:space:]]*air|non.?operational|non.?aktif|dismantle|unlock|relokasi|migrasi'");
                } elseif ($value === 'contract') {
                    $status = "LOWER(CONCAT(COALESCE(status_dokumen, ''), ' ', COALESCE(status_perpanjangan, '')))";
                    $subQuery->whereRaw("{$status} REGEXP 'nego|pending|belum|proses|legal|perpanjang|finalisasi'");
                } elseif ($value === 'off_air') {
                    $subQuery->whereRaw("{$status} REGEXP 'off[[:space:]]*air|non.?operational|non.?aktif|dismantle|unlock|relokasi|migrasi'");
                } elseif ($value === 'without_pks') {
                    $subQuery->where(function ($empty): void { $empty->whereNull('no_pks_baru')->orWhere('no_pks_baru', ''); })
                        ->where(function ($empty): void { $empty->whereNull('no_pks_lama')->orWhere('no_pks_lama', ''); });
                }
            });
        } elseif ($field === 'status_perpanjangan') {
            $value === 'Tidak Diisi'
                ? $query->where(function ($subQuery): void { $subQuery->whereNull('status_perpanjangan')->orWhere('status_perpanjangan', ''); })
                : $query->where('status_perpanjangan', $value);
        } elseif ($field === 'status_masa_sewa') {
            $this->applyLeaseStatusFilter($query, $value);
        } elseif ($field === 'status') {
            $status = "LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(source_details, '$.status')), ''))";
            if ($value === InfrastructureMetrics::ACTIVE) {
                $query->whereRaw("{$status} REGEXP 'on[[:space:]]*air|active|operational'")
                    ->whereRaw("{$status} NOT REGEXP 'off[[:space:]]*air|non.?operational|non.?aktif|dismantle|unlock|relokasi|migrasi'");
            } elseif ($value === InfrastructureMetrics::OFF_AIR) {
                $query->whereRaw("{$status} REGEXP 'off[[:space:]]*air|non.?operational|non.?aktif|dismantle|unlock|relokasi|migrasi'");
            } elseif ($value === InfrastructureMetrics::UNKNOWN_STATUS || $value === 'Tidak Diisi') {
                $query->where(function ($subQuery): void {
                    $subQuery->where(function ($empty): void {
                        $empty->whereNull('source_details->status')->orWhereJsonContains('source_details->status', '');
                    });
                });
            } else {
                $query->whereJsonContains('source_details->status', $value);
            }
        } elseif ($field === 'status_dokumen') {
            $value === 'Tidak Diisi'
                ? $query->where(function ($subQuery): void { $subQuery->whereNull('status_dokumen')->orWhere('status_dokumen', ''); })
                : $query->where('status_dokumen', $value);
        } elseif ($field === 'site_code') {
            $query->where('site_code', $value);
        } elseif ($field === 'pipeline') {
            $this->applyPipelineFilter($query, $value);
        } elseif ($field === 'geography') {
            $this->applyGeographyFilter($query, $value);
        } elseif ($field === 'priority') {
            $this->applyPriorityFilter($query);
        } elseif ($field === 'lease_window') {
            $endDate = 'COALESCE(end_date_baru, end_date_lama)';
            $query->whereRaw("{$endDate} BETWEEN ? AND ?", [today()->toDateString(), today()->addDays(180)->toDateString()]);
        } elseif ($field !== '' && $value !== '' && in_array($field, ['pks_status', 'nop', 'vendor', 'ownership'], true)) {
            if ($field === 'vendor') {
                $query->where(function ($subQuery) use ($value): void {
                    if ($value === 'Tidak Diisi') {
                        $subQuery->where(function ($empty): void {
                            $empty->whereNull('source_details->vendor')->orWhereJsonContains('source_details->vendor', '');
                        })->where(function ($empty): void {
                            $empty->whereNull('source_details->tp')->orWhereJsonContains('source_details->tp', '');
                        });
                    } else {
                        $subQuery->whereJsonContains('source_details->vendor', $value)
                            ->orWhereJsonContains('source_details->tp', $value);
                    }
                });
            } elseif ($field === 'ownership') {
                $query->where(function ($subQuery) use ($value): void {
                    $needle = strtolower(trim($value));
                    $sourceNeedle = $needle === 'telkomsel' ? '%telkomsel%' : ($needle === 'tp' ? '%tp%' : null);
                    if ($sourceNeedle !== null) {
                        $subQuery->whereRaw("LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(source_details, '$.ownership')), '')) LIKE ?", [$sourceNeedle])
                            ->orWhereRaw("LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(source_details, '$.tp')), '')) LIKE ?", [$sourceNeedle]);
                    }

                    $ownerCodes = SiteOwner::query()
                        ->where(function ($ownerQuery) use ($needle): void {
                            if ($needle === 'telkomsel') {
                                $ownerQuery->whereRaw("LOWER(COALESCE(site_owner, '')) LIKE '%telkomsel%'");
                            } elseif ($needle === 'tp') {
                                $ownerQuery->whereRaw("LOWER(COALESCE(site_owner, '')) LIKE '%tp%'")
                                    ->orWhereRaw("LOWER(COALESCE(site_owner, '')) LIKE '%tower%'");
                            } else {
                                $ownerQuery->whereNull('site_owner')
                                    ->orWhere(function ($other) {
                                        $other->whereRaw("LOWER(COALESCE(site_owner, '')) NOT LIKE '%telkomsel%'")
                                            ->whereRaw("LOWER(COALESCE(site_owner, '')) NOT LIKE '%tp%'")
                                            ->whereRaw("LOWER(COALESCE(site_owner, '')) NOT LIKE '%tower%'");
                                    });
                            }
                        })
                        ->pluck('site_code')
                        ->filter()
                        ->values();

                    if ($ownerCodes->isNotEmpty()) {
                        $subQuery->orWhereIn('site_code', $ownerCodes->all());
                    }

                    if ($sourceNeedle === null) {
                        // Any row whose dataset ownership is neither
                        // Telkomsel nor TP belongs to the fallback bucket,
                        // including sites that have no SiteOwner lookup row.
                        $subQuery->orWhere(function ($fallback): void {
                            $fallback->whereRaw("LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(source_details, '$.ownership')), '')) NOT LIKE '%telkomsel%'")
                                ->whereRaw("LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(source_details, '$.ownership')), '')) NOT LIKE '%tp%'")
                                ->whereRaw("LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(source_details, '$.ownership')), '')) NOT LIKE '%tower%'")
                                ->whereRaw("LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(source_details, '$.tp')), '')) NOT LIKE '%telkomsel%'")
                                ->whereRaw("LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(source_details, '$.tp')), '')) NOT LIKE '%tp%'")
                                ->whereRaw("LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(source_details, '$.tp')), '')) NOT LIKE '%tower%'");
                        });
                    }
                });
            } elseif ($field === 'pks_status') {
                if ($value === 'Ada PKS') {
                    $query->where(function ($subQuery): void {
                        $subQuery->where(function ($filled): void {
                            $filled->whereNotNull('no_pks_baru')->where('no_pks_baru', '<>', '');
                        })->orWhere(function ($filled): void {
                            $filled->whereNotNull('no_pks_lama')->where('no_pks_lama', '<>', '');
                        });
                    });
                } elseif ($value === 'Tanpa PKS') {
                    $query->where(function ($empty): void { $empty->whereNull('no_pks_baru')->orWhere('no_pks_baru', ''); })
                        ->where(function ($empty): void { $empty->whereNull('no_pks_lama')->orWhere('no_pks_lama', ''); });
                } elseif ($value === 'Tidak Diisi') {
                    $query->where(function ($subQuery): void {
                        $subQuery->where(function ($empty): void {
                            $empty->whereNull('source_details->pks_status')->orWhereJsonContains('source_details->pks_status', '');
                        })->where(function ($empty): void {
                            $empty->whereNull('status_dokumen')->orWhere('status_dokumen', '');
                        });
                    });
                } else {
                    $query->where(function ($subQuery) use ($value): void {
                        $subQuery->whereJsonContains('source_details->pks_status', $value)
                            ->orWhere('status_dokumen', $value);
                    });
                }
            } elseif ($field === 'nop') {
                if ($value === 'Tidak Diisi') {
                    $query->where(function ($subQuery): void {
                        $subQuery->whereNull('source_details->nop')->orWhereJsonContains('source_details->nop', '');
                    });
                } else {
                    $query->whereJsonContains('source_details->nop', $value);
                }
            } else {
                $query->whereJsonContains("source_details->{$field}", $value);
            }
        }
    }

    private function applyPipelineFilter($query, string $value): void
    {
        $stage = "LOWER(COALESCE(NULLIF(status_perpanjangan, ''), NULLIF(status_dokumen, ''), ''))";

        match ($value) {
            'Paid' => $query->whereRaw("{$stage} REGEXP 'paid|bayar'"),
            'Drop' => $query->whereRaw("{$stage} REGEXP 'drop|dismantle|relokasi'"),
            'Negosiasi' => $query->whereRaw("{$stage} REGEXP 'negos'"),
            'BAK' => $query->whereRaw("{$stage} REGEXP 'bak'"),
            'Pending PKS' => $query->whereRaw("{$stage} REGEXP 'pending.*pks|pks.*pending'"),
            'PKS' => $query->whereRaw("{$stage} REGEXP 'pks|legal'")
                ->whereRaw("{$stage} NOT REGEXP 'pending.*pks|pks.*pending'"),
            'Budget' => $query->whereRaw("{$stage} REGEXP 'budget'"),
            'Finance' => $query->whereRaw("{$stage} REGEXP 'financ'"),
            'Tidak Diisi' => $query->whereRaw("{$stage} = ''"),
            default => $query->whereRaw("{$stage} <> ''")
                ->whereRaw("{$stage} NOT REGEXP 'paid|bayar|drop|dismantle|relokasi|negos|bak|pks|legal|budget|financ'"),
        };
    }

    private function applyGeographyFilter($query, string $value): void
    {
        $query->where(function ($location) use ($value): void {
            foreach (['kabupaten', 'city', 'kota', 'area', 'wilayah'] as $key) {
                $location->orWhereRaw("TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(source_details, '$.{$key}')), '')) = ?", [$value]);
            }
        });
    }

    private function applyPriorityFilter($query): void
    {
        $attention = "LOWER(CONCAT(COALESCE(status_dokumen, ''), ' ', COALESCE(status_perpanjangan, '')))";
        $endDate = 'COALESCE(end_date_baru, end_date_lama)';

        $query->where(function ($priority) use ($attention, $endDate): void {
            $priority->whereRaw("{$attention} REGEXP 'nego|pending|belum|proses|legal|perpanjang|finalisasi'")
                ->orWhere(function ($withoutPks): void {
                    $withoutPks->where(function ($empty): void { $empty->whereNull('no_pks_baru')->orWhere('no_pks_baru', ''); })
                        ->where(function ($empty): void { $empty->whereNull('no_pks_lama')->orWhere('no_pks_lama', ''); });
                })
                ->orWhereNull('end_date_baru')->whereNull('end_date_lama')
                ->orWhereRaw("{$endDate} <= ?", [today()->addDays(180)->toDateString()]);
        });
    }

    private function applyLeaseStatusFilter($query, string $value): void
    {
        $endDate = 'COALESCE(end_date_baru, end_date_lama)';
        $today = today()->toDateString();

        match ($value) {
            LeaseStatus::NO_END_DATE => $query->whereNull('end_date_baru')->whereNull('end_date_lama'),
            LeaseStatus::EXPIRED => $query->whereRaw("{$endDate} < ?", [$today]),
            LeaseStatus::WITHIN_90_DAYS => $query->whereRaw("{$endDate} BETWEEN ? AND ?", [$today, today()->addDays(90)->toDateString()]),
            LeaseStatus::WITHIN_180_DAYS => $query->whereRaw("{$endDate} BETWEEN ? AND ?", [today()->addDays(91)->toDateString(), today()->addDays(180)->toDateString()]),
            LeaseStatus::SAFE => $query->whereRaw("{$endDate} > ?", [today()->addDays(180)->toDateString()]),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Form edit Combat site.
     */
    public function edit(CombatSite $combat): View
    {
        return view('infrastruktur.combat.edit', compact('combat'));
    }

    /**
     * Simpan perubahan Combat site.
     */
    public function update(UpdateCombatSiteRequest $request, CombatSite $combat): RedirectResponse
    {
        $data = $request->validated();
        $data['update_by'] = auth()->user()->name;
        $data['tanggal']   = now();

        $combat->update($data);

        return redirect()
            ->route('infrastruktur.combat.index')
            ->with('success', "Combat site {$combat->site_code} berhasil diperbarui.");
    }

    /**
     * Soft delete Combat site.
     */
    public function destroy(CombatSite $combat): RedirectResponse
    {
        $siteCode = $combat->site_code;
        $combat->delete();

        return redirect()
            ->route('infrastruktur.combat.index')
            ->with('success', "Combat site {$siteCode} berhasil dihapus.");
    }

    /**
     * Download Excel — filter sesuai tahun aktif.
     */
    public function exportExcel(Request $request)
    {
        $tahun = $request->filled('tahun') && $request->tahun !== 'all'
            ? (int) $request->tahun
            : null;

        $filename = 'combat_sites' . ($tahun ? "_{$tahun}" : '_all') . '.xlsx';

        return (new CombatSiteExport($tahun))->download($filename);
    }
}
