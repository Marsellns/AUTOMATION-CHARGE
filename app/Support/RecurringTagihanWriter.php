<?php

namespace App\Support;

use App\Models\RecurringTagihanIpas;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecurringTagihanWriter
{
    public function store(array $records, bool $rejectExisting = false): void
    {
        DB::transaction(function () use ($records, $rejectExisting): void {
            $ids = [];
            foreach ($records as $record) {
                $code = RecurringTagihanSummary::siteCode($record['site_code']);
                $site = RecurringTagihanIpas::firstOrCreate(['site_code' => $code]);
                // Serialize writers for the same site before refreshing its summary.
                DB::table('recurring_tagihan_ipas')->where('id', $site->id)->lockForUpdate()->first();
                $record['site_id'] = $site->id;
                $record['site_code'] = $code;
                $details = $record['source_details'] ?? [];
                if (is_string($details)) {
                    $details = json_decode($details, true, flags: JSON_THROW_ON_ERROR);
                }
                $record['source_details'] = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                // Source Recurring ID distinguishes parallel contracts and invoices.
                $query = DB::table('recurring_tagihan_ipas_terms')->where('site_id', $site->id);
                if (! empty($details['recurring'])) {
                    $query->where('source_details->recurring', (string) $details['recurring']);
                } else {
                    foreach (['termin', 'periode_ke', 'termin_start', 'tp', 'contract_type'] as $column) {
                        $query->where($column, $record[$column] ?? null);
                    }
                    foreach (['infra_contract', 'contract_no', 'sow_detail'] as $column) {
                        $value = $details[$column] ?? null;
                        if ($value !== null && $value !== '') {
                            $query->where('source_details->'.$column, $value);
                        }
                    }
                }
                $matches = $query->limit(2)->get();
                if ($matches->count() > 1) {
                    throw ValidationException::withMessages(['dataset_file' => 'Ada beberapa kontrak untuk termin ini. Gunakan Download Rincian CSV agar Recurring ID sumber tetap disertakan.']);
                }
                $existing = $matches->first();
                if ($existing && $rejectExisting) {
                    throw ValidationException::withMessages(['site_code' => 'Site dengan identitas kontrak/periode yang sama sudah ada. Periksa data yang sudah tersimpan.'])
                        ->errorBag('infrastructureManual');
                }
                $record['updated_at'] = now();
                if ($existing) {
                    unset($record['created_at']);
                    $previous = json_decode($existing->source_details ?? '{}', true, flags: JSON_THROW_ON_ERROR);
                    $record['source_details'] = json_encode(array_replace($previous ?? [], $details), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                    DB::table('recurring_tagihan_ipas_terms')->where('id', $existing->id)->update($record);
                } else {
                    $record['created_at'] ??= now();
                    DB::table('recurring_tagihan_ipas_terms')->insert($record);
                }
                $ids[] = $site->id;
            }
            RecurringTagihanSummary::refresh($ids);
        });
    }
}
