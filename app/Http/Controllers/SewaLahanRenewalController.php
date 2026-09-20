<?php

namespace App\Http\Controllers;

use App\Exports\SewaLahanRenewalExport;
use App\Http\Requests\UpdateSewaLahanRenewalRequest;
use App\Models\SewaLahanRenewal;
use App\Models\SiteOwner;
use App\Support\LeaseStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class SewaLahanRenewalController extends Controller
{
    /**
     * Halaman utama modul Sewa Lahan (Renewal): tabel DataTables server-side,
     * filter tahun, search granular per kolom, dan expand detail inline.
     */
    public function index(): View
    {
        $tahunList = SewaLahanRenewal::query()
            ->whereNotNull('tahun_renewal')
            ->distinct()
            ->orderByDesc('tahun_renewal')
            ->pluck('tahun_renewal');

        return view('infrastruktur.sewa-lahan.index', compact('tahunList'));
    }

    /**
     * Sumber data DataTables (server-side processing).
     * Mendukung filter tahun + search granular per kolom (LIKE dari Yajra).
     */
    public function data(Request $request): JsonResponse
    {
        $isAdmin = auth()->user()->hasRole('admin');

        $query = SewaLahanRenewal::query()->latest('id');

        // Filter tahun renewal
        if ($request->filled('tahun') && $request->tahun !== 'all') {
            $query->where('tahun_renewal', (int) $request->tahun);
        }
        $this->applyDashboardFilter($query, $request);
        $ownershipScope = (string) $request->input('ownership_scope');
        if (in_array($ownershipScope, ['Telkomsel', 'TP'], true)
            && ! ($request->input('filter_field') === 'ownership' && $request->input('filter_value') === $ownershipScope)) {
            $this->applyOwnershipFilter($query, $ownershipScope);
        }
        if ($request->boolean('unique_sites')) {
            $uniqueIds = (clone $query)
                ->reorder()
                ->select([])
                ->selectRaw('MIN(id)')
                ->groupBy('site_code');
            $query->whereIn('sewa_lahan_renewals.id', $uniqueIds);
        }

        $dataTable = DataTables::of($query);

        if ($isAdmin) {
            $dataTable->addColumn(
                'aksi',
                fn (SewaLahanRenewal $s) => view('infrastruktur.sewa-lahan.partials.aksi', ['sewaLahan' => $s])->render()
            );
        }

        return $dataTable
            ->addIndexColumn()
            ->rawColumns($isAdmin ? ['aksi'] : [])
            ->toJson();
    }

    private function applyDashboardFilter($query, Request $request): void
    {
        $field = (string) $request->input('filter_field');
        $value = (string) $request->input('filter_value');
        if ($field === 'tahun') {
            $value === 'Tidak Diisi'
                ? $query->whereNull('tahun_renewal')
                : $query->where('tahun_renewal', (int) $value);
        } elseif ($field === 'summary_status') {
            $query->where(function ($subQuery) use ($value): void {
                $status = "LOWER(CONCAT(COALESCE(status_dokumen, ''), ' ', COALESCE(status_perpanjangan, '')))";
                if ($value === 'active') {
                    $subQuery->whereRaw("{$status} NOT REGEXP 'dismantle|off[[:space:]]*air|non.?operational|non.?aktif|unlock|relokasi|migrasi'");
                } elseif ($value === 'contract') {
                    $subQuery->whereRaw("{$status} REGEXP 'nego|pending|belum|proses|legal|perpanjang|finalisasi'");
                } elseif ($value === 'off_air') {
                    $subQuery->whereRaw("{$status} REGEXP 'dismantle|off[[:space:]]*air|non.?operational|non.?aktif|unlock|relokasi|migrasi'");
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
            if ($value === 'Tidak Diisi') {
                $query->where(function ($subQuery): void {
                    $subQuery->where(function ($empty): void {
                        $empty->whereNull('source_details->status')->orWhereJsonContains('source_details->status', '');
                    })->where(function ($empty): void {
                        $empty->whereNull('status_dokumen')->orWhere('status_dokumen', '');
                    });
                });
            } else {
                $query->where(function ($subQuery) use ($value): void {
                    $subQuery->whereJsonContains('source_details->status', $value)
                        ->orWhere('status_dokumen', $value);
                });
            }
        } elseif ($field === 'status_dokumen') {
            $value === 'Tidak Diisi'
                ? $query->where(function ($subQuery): void { $subQuery->whereNull('status_dokumen')->orWhere('status_dokumen', ''); })
                : $query->where('status_dokumen', $value);
        } elseif ($field === 'site_code') {
            $query->where('site_code', $value);
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
                $this->applyOwnershipFilter($query, $value);
            } else {
                if ($field === 'pks_status' && $value === 'Tidak Diisi') {
                    $query->where(function ($subQuery): void {
                        $subQuery->where(function ($empty): void {
                            $empty->whereNull('source_details->pks_status')->orWhereJsonContains('source_details->pks_status', '');
                        })->where(function ($empty): void {
                            $empty->whereNull('status_dokumen')->orWhere('status_dokumen', '');
                        });
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
                    } else {
                        $query->where(function ($subQuery) use ($value): void {
                            $subQuery->whereJsonContains('source_details->pks_status', $value)
                                ->orWhere('status_dokumen', $value);
                        });
                    }
                } elseif ($field === 'nop' && $value === 'Tidak Diisi') {
                    $query->where(function ($subQuery): void {
                        $subQuery->whereNull('source_details->nop')->orWhereJsonContains('source_details->nop', '');
                    });
                } else {
                    $query->whereJsonContains("source_details->{$field}", $value);
                }
            }
        }
    }

    private function applyOwnershipFilter($query, string $value): void
    {
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
     * Form edit Sewa Lahan renewal.
     */
    public function edit(SewaLahanRenewal $sewaLahan): View
    {
        return view('infrastruktur.sewa-lahan.edit', compact('sewaLahan'));
    }

    /**
     * Simpan perubahan Sewa Lahan renewal.
     */
    public function update(UpdateSewaLahanRenewalRequest $request, SewaLahanRenewal $sewaLahan): RedirectResponse
    {
        $data = $request->validated();
        $data['update_by']  = auth()->user()->name;
        $data['tgl_update'] = now();

        $sewaLahan->update($data);

        return redirect()
            ->route('infrastruktur.sewa-lahan.index')
            ->with('success', "Sewa Lahan site {$sewaLahan->site_code} berhasil diperbarui.");
    }

    /**
     * Soft delete Sewa Lahan renewal.
     */
    public function destroy(SewaLahanRenewal $sewaLahan): RedirectResponse
    {
        $siteCode = $sewaLahan->site_code;
        $sewaLahan->delete();

        return redirect()
            ->route('infrastruktur.sewa-lahan.index')
            ->with('success', "Sewa Lahan site {$siteCode} berhasil dihapus.");
    }

    /**
     * Download Excel — seluruh data atau sesuai filter tahun aktif.
     */
    public function exportExcel(Request $request)
    {
        $tahun = $request->filled('tahun') && $request->tahun !== 'all'
            ? (int) $request->tahun
            : null;

        $filename = 'sewa_lahan_renewals' . ($tahun ? "_{$tahun}" : '_all') . '.xlsx';

        return (new SewaLahanRenewalExport($tahun))->download($filename);
    }

    /**
     * Cetak SIP — generate PDF dari data baris (template sederhana,
     * akan disempurnakan kemudian).
     */
    public function cetakSip(SewaLahanRenewal $sewaLahan)
    {
        $pdf = Pdf::loadView('infrastruktur.sewa-lahan.sip', compact('sewaLahan'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('SIP_' . $sewaLahan->site_code . '.pdf');
    }
}
