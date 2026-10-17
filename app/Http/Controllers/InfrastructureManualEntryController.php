<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInfrastructureManualEntryRequest;
use App\Support\InfrastructureManualEntry;
use App\Support\RecurringTagihanWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class InfrastructureManualEntryController extends Controller
{
    public function store(StoreInfrastructureManualEntryRequest $request, string $dataset): RedirectResponse
    {
        $definition = InfrastructureManualEntry::DATASETS[$dataset];
        $data = $request->validated();
        if (in_array($dataset, ['sewa-lahan', 'combat'], true)) {
            $details = $data['source_details'] ?? [];
            $year = $data['performance_year'] ?? now()->year;
            foreach ($data['performance'] ?? [] as $month => $values) {
                foreach ($values ?? [] as $metric => $value) {
                    if ($value !== null) {
                        $details["{$metric}_{$month}_{$year}"] = $value;
                    }
                }
            }
            $data['source_details'] = $details;
        }
        $data = Arr::except($data, ['performance', 'performance_year', 'pdf_bapss_file', 'pdf_ba_dismantle_file']);
        if (in_array($dataset, ['sewa-lahan', 'combat', 'bapss', 'site-unlock'], true)) {
            $data['update_by'] = $request->user()->name;
        }
        if (in_array($dataset, ['sewa-lahan', 'bapss', 'recurring-ipas', 'jaknet'], true)) {
            $data['tgl_update'] = now();
        }
        if ($dataset === 'combat') {
            $data['tanggal'] = now();
        }
        if ($dataset === 'jaknet' && empty($data['tahun_berakhir']) && ! empty($data['tanggal_berakhir'])) {
            $data['tahun_berakhir'] = (int) substr($data['tanggal_berakhir'], 0, 4);
        }

        $paths = [];
        try {
            DB::transaction(function () use ($request, $definition, $dataset, &$data, &$paths): void {
                if ($dataset === 'recurring-tagihan-ipas') {
                    app(RecurringTagihanWriter::class)->store([$data], rejectExisting: true);

                    return;
                }
                // Never update an imported row when the user chooses Tambah Manual.
                $existing = $definition['model']::query()->whereRaw('UPPER(TRIM(site_code)) = ?', [$data['site_code']]);
                foreach (array_slice($definition['key'], 1) as $key) {
                    $value = $data[$key] ?? null;
                    $value === null ? $existing->whereNull($key) : $existing->where($key, $value);
                }
                if ($existing->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['site_code' => 'Site dengan identitas kontrak/periode yang sama sudah ada. Periksa data yang sudah tersimpan.'])
                        ->errorBag('infrastructureManual');
                }
                foreach ($dataset === 'bapss' ? ['pdf_bapss', 'pdf_ba_dismantle'] : [] as $column) {
                    if ($request->hasFile($column.'_file')) {
                        $path = $request->file($column.'_file')->store('bapss', 'private');
                        if ($path === false) {
                            throw new \RuntimeException('PDF tidak dapat disimpan.');
                        }
                        $paths[] = $path;
                        $data[$column] = $path;
                    }
                }
                $definition['model']::create($data);
            });
        } catch (Throwable $exception) {
            foreach ($paths as $path) {
                Storage::disk('private')->delete($path);
            }
            throw $exception;
        }
        $query = in_array($dataset, ['sewa-lahan', 'combat'], true) ? ['filter_field' => 'site_code', 'filter_value' => $data['site_code']] : [];
        if ($dataset === 'jaknet' && ! empty($data['is_site_unlock'])) {
            $query['show_unlock'] = 1;
        }

        return redirect()->route('infrastruktur.'.$dataset.'.index', $query)
            ->with('success', "{$definition['label']} site {$data['site_code']} berhasil ditambahkan secara manual.");
    }
}
