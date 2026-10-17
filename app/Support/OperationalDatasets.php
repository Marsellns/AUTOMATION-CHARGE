<?php

namespace App\Support;

use App\Models;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Allowlisted inputs for existing module tables, shared by manual and spreadsheet entry. */
final class OperationalDatasets
{
    private static array $fieldCache = [];

    public const DEFINITIONS = [
        'site-owner' => [Models\SiteOwner::class, 'Site Owner', 'data-potensi.site-owner.index', ['site_code'], ['site_code']],
        'asset-tower' => [Models\DataAssetTower::class, 'Asset Tower', 'data-potensi.asset-tower.index', ['site_id', 'site_company', 'brand', 'part_name', 'owner'], ['site_id']],
        'po-hq' => [Models\PoHq::class, 'PO HQ', 'po-hq.index', ['po_number'], ['po_number', 'vendor_name', 'status']],
        'po-varcost' => [Models\PoVarcost::class, 'PO Varcost', 'po-varcost.index', ['po_number'], ['po_number']],
        'inventory' => [Models\EquipmentRelocationInventory::class, 'Inventaris Equipment', 'equipment-relocation.index', ['uniq_key'], ['uniq_key', 'site_id']],
        'pnl' => [Models\SiteMonthlyMetric::class, 'Profit & Loss', 'pnl.index', ['site_code', 'bulan', 'tahun'], ['site_code', 'bulan', 'tahun', 'revenue', 'cost']],
        'input-ibc' => [Models\InputUploadTagihanIbc::class, 'Tagihan IBC', 'electricity.inbuilding.input-tagihan-ibc.index', ['site_id', 'periode', 'type', 'no_invoice_bm'], ['site_id', 'periode', 'type']],
        'listrik-pln' => [Models\ListrikPln::class, 'Master Listrik PLN', 'electricity.centralized.listrik-pln.index', ['id_pelanggan', 'site_id'], ['id_pelanggan', 'site_id']],
    ];

    public static function fields(string $dataset): array
    {
        $cacheKey = DB::connection()->getDatabaseName().':'.$dataset;
        if (isset(self::$fieldCache[$cacheKey])) {
            return self::$fieldCache[$cacheKey];
        }
        [$class, , , , $required] = self::DEFINITIONS[$dataset];
        $model = new $class;
        $columns = collect(Schema::getColumns($model->getTable()))->keyBy('name');
        $excluded = ['update_by', 'update_at', 'tgl_update', 'tanggal_update', 'total_tagihan_f', 'total_payment_k', 'is_anomaly'];
        $names = array_diff($model->getFillable(), $excluded);
        if ($dataset === 'pnl') {
            $names = ['site_code', 'site_name', 'bulan', 'tahun', 'revenue', 'cost', ...array_diff($names, ['site_id', 'bulan', 'tahun', 'revenue', 'cost'])];
        }
        $fields = [];
        foreach ($names as $name) {
            $column = $columns->get($name);
            $sql = strtolower($column['type'] ?? 'varchar(255)');
            $type = str_contains($sql, 'date') ? 'date' : (preg_match('/int|decimal|float|double/', $sql) ? 'number' : 'text');
            $isRequired = in_array($name, $required, true) || ($column && ! $column['nullable'] && $column['default'] === null);
            $rules = [$isRequired ? 'required' : 'nullable'];
            if ($type === 'number') {
                $rules[] = str_contains($sql, 'int') ? 'integer' : 'numeric';
                if ($dataset !== 'pnl') {
                    $rules[] = 'min:0';
                }
                if (preg_match('/decimal\((\d+),(\d+)\)/', $sql, $m)) {
                    $rules[] = 'decimal:0,'.$m[2];
                    $rules[] = 'max:'.(10 ** ((int) $m[1] - (int) $m[2]) - 1);
                    if ($dataset === 'pnl') {
                        $rules[] = 'min:'.(-(10 ** ((int) $m[1] - (int) $m[2]) - 1));
                    }
                } else {
                    $rules[] = 'max:4294967295';
                }
            } elseif ($type === 'date') {
                $rules[] = 'date';
            } else {
                $rules[] = 'string';
                $rules[] = 'max:'.(preg_match('/varchar\((\d+)\)/', $sql, $m) ? $m[1] : (str_contains($sql, 'text') ? 5000 : 255));
            }
            if ($name === 'tahun' || $name === 'po_year') {
                $rules = [$rules[0], 'integer', 'between:2000,2100'];
            }
            if ($name === 'bulan') {
                $rules = ['required', 'integer', 'between:1,12'];
            }
            if ($name === 'periode') {
                $rules = ['required', 'date_format:Y-m'];
            }
            $choices = match ($name) {
                'expense_type' => ['Capex', 'Opex'],
                'status' => $dataset === 'po-hq' ? Models\PoHq::STATUSES : null,
                'status_aktif_site' => Models\ListrikPln::STATUS_AKTIF_SITE,
                'type' => $dataset === 'input-ibc' ? Models\InputUploadTagihanIbc::TYPES : null,
                'safe_to_reloc' => ['SAFE', 'NOT SAFE', 'OK', 'NOT OK'],
                default => null,
            };
            if ($choices) {
                $rules[] = Rule::in($choices);
            }
            if ($dataset === 'input-ibc' && $name === 'site_id') {
                $rules[] = 'exists:listrik_inbuilding,site_id';
            }
            $fields[$name] = ['label' => match ($name) {
                'site_code','site_id' => 'Site ID', 'uniq_key' => 'Kode Unik Equipment', default => Str::headline($name)
            },
                'type' => $type, 'rules' => $rules, 'required' => $isRequired, 'choices' => $choices,
                'nullable' => $column['nullable'] ?? true, 'default' => $column['default'] ?? null];
        }

        return self::$fieldCache[$cacheKey] = $fields;
    }

    public static function save(string $dataset, array $input, bool $createOnly, string $actor): void
    {
        [$class, , , $key] = self::DEFINITIONS[$dataset];
        $fields = self::fields($dataset);
        foreach ($fields as $name => $field) {
            if (array_key_exists($name, $input) && ($input[$name] === null || $input[$name] === '') && ! $field['nullable'] && $field['default'] !== null) {
                $input[$name] = trim((string) $field['default'], "'");
            }
        }
        foreach (['site_code', 'site_id'] as $id) {
            if (isset($input[$id]) && is_scalar($input[$id])) {
                $input[$id] = strtoupper(trim((string) $input[$id]));
            }
        }
        $data = Validator::make($input, array_map(fn ($field) => $field['rules'], $fields))->validate();
        DB::transaction(function () use ($dataset, $class, $key, $data, $createOnly, $actor): void {
            if ($dataset === 'pnl') {
                $owner = Models\SiteOwner::firstOrCreate(['site_code' => $data['site_code']], ['site_name' => $data['site_name'] ?? null]);
                $site = Models\Site::firstOrCreate(['site_id' => $data['site_code']], ['site_name' => $data['site_name'] ?? $owner->site_name]);
                $data['site_id'] = $site->id;
                unset($data['site_code'],$data['site_name']);
                $key = ['site_id', 'bulan', 'tahun'];
                $data['is_anomaly'] = (float) $data['revenue'] === 2147483647.0 || (float) $data['cost'] === 2147483647.0;
            }
            $query = $class::query();
            foreach ($key as $column) {
                $query->where($column, $data[$column] ?? null);
            }
            $record = $query->lockForUpdate()->first();
            if ($record && $createOnly) {
                throw ValidationException::withMessages([array_key_first($data) => 'Data dengan identitas yang sama sudah ada. Gunakan edit atau Excel untuk memperbarui.']);
            }
            $record ??= new $class;
            if ($dataset === 'input-ibc') {
                $bill = array_replace($record->getAttributes(), $data);
                $total = array_sum(array_map(fn ($field) => (float) ($bill[$field] ?? 0), ['dpp_ppn_b', 'ppj_pju_c', 'materai_d', 'others_e']));
                $tax = (float) ($bill['wht_j'] ?? 0) ?: (float) ($bill['dpp_wht_i'] ?? 0);
                $data['total_tagihan_f'] = $total;
                $data['total_payment_k'] = max(0, $total + (float) ($bill['gross_up_g'] ?? 0) + (float) ($bill['ppn_h'] ?? 0) - $tax);
            }
            $fillable = $record->getFillable();
            foreach (['update_by' => $actor, 'update_at' => now(), 'tgl_update' => now(), 'tanggal_update' => now()] as $field => $value) {
                if (in_array($field, $fillable, true)) {
                    $data[$field] = $value;
                }
            }
            $record->fill($data)->save();
            if ($dataset === 'site-owner') {
                Models\Site::firstOrCreate(['site_id' => $data['site_code']], ['site_name' => $data['site_name'] ?? null]);
            }
        });
    }
}
