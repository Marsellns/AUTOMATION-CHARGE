<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Live operational projections. Imported source tables remain intact. */
final class ElectricityReadViews
{
    public const VIEWS = ['payment_pln_effective', 'payment_ibc_effective', 'payment_pln_master_monthly_effective', 'inbuilding_all_effective', 'anomali_tagihan_pln_effective', 'anomali_tagihan_inbuilding_effective'];

    public static function create(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW payment_pln_effective AS
            SELECT p.* FROM payment_pln p WHERE NOT EXISTS (
                SELECT 1 FROM status_pembayaran s JOIN listrik_pln l ON l.id=s.listrik_pln_id
                WHERE s.is_operational=1 AND s.tahun=p.tahun AND s.bulan=p.bulan
                  AND UPPER(TRIM(l.site_id))=UPPER(TRIM(p.site_id))
                  AND UPPER(TRIM(s.id_pelanggan))=UPPER(TRIM(p.id_pelanggan))
            )
            UNION ALL
            SELECT -s.id, s.id_pelanggan, l.site_id, l.site_name, l.daya_va, l.phasa,
                   l.gol_tarif, l.unit_layanan_pln, s.harga, s.update_by, s.tanggal,
                   CASE WHEN LOWER(COALESCE(s.remark,'')) REGEXP 'pending|belum|unpaid' THEN 'Pending' ELSE 'Done' END,
                   s.bulan, s.tahun, s.created_at, s.updated_at
            FROM status_pembayaran s JOIN listrik_pln l ON l.id=s.listrik_pln_id WHERE s.is_operational=1
            SQL);
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW payment_ibc_effective AS
            SELECT p.* FROM payment_ibc p WHERE NOT EXISTS (
                SELECT 1 FROM input_upload_tagihan_ibc i
                WHERE UPPER(TRIM(i.site_id))=UPPER(TRIM(p.site_id))
                  AND i.periode=CONCAT(p.tahun,'-',LPAD(p.bulan,2,'0'))
                  AND (NULLIF(TRIM(i.no_invoice_bm),'') IS NULL OR UPPER(TRIM(i.no_invoice_bm))=UPPER(TRIM(p.invoice)))
            )
            UNION ALL
            SELECT -i.id, i.site_id, l.site_name, l.nama_bm, l.daya,
                   CASE WHEN i.tgl_bayar_to_bm IS NULL THEN 'Pending' ELSE 'Done' END,
                   i.total_tagihan_f, COALESCE(NULLIF(TRIM(i.no_invoice_bm),''),i.no_invoice_rpj), NULL,
                   i.tgl_bayar_to_bm, CAST(SUBSTR(i.periode,6,2) AS UNSIGNED),
                   CAST(SUBSTR(i.periode,1,4) AS UNSIGNED), i.created_at, i.updated_at
            FROM input_upload_tagihan_ibc i
            LEFT JOIN (SELECT UPPER(TRIM(site_id)) AS code, MAX(site_name) AS site_name,
                       MAX(nama_bm) AS nama_bm, MAX(daya) AS daya FROM listrik_inbuilding GROUP BY UPPER(TRIM(site_id))) l
              ON l.code=UPPER(TRIM(i.site_id))
            WHERE i.periode REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$'
              AND (NULLIF(TRIM(i.no_invoice_bm),'') IS NULL OR NOT EXISTS (
                  SELECT 1 FROM input_upload_tagihan_ibc total
                  WHERE UPPER(TRIM(total.site_id))=UPPER(TRIM(i.site_id)) AND total.periode=i.periode
                    AND NULLIF(TRIM(total.no_invoice_bm),'') IS NULL
              ))
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW payment_pln_master_monthly_effective AS
            SELECT p.* FROM payment_pln_master_monthly p WHERE NOT EXISTS (
                SELECT 1 FROM status_pembayaran s JOIN listrik_pln l ON l.id=s.listrik_pln_id
                WHERE s.is_operational=1 AND s.tahun=p.tahun AND s.bulan=p.bulan
                  AND UPPER(TRIM(l.site_id))=UPPER(TRIM(p.site_id))
                  AND UPPER(TRIM(s.id_pelanggan))=UPPER(TRIM(p.id_pelanggan))
            )
            UNION ALL
            SELECT -s.id,s.id_pelanggan,l.site_id,l.site_name,s.bulan,s.tahun,s.harga,
                   CASE WHEN LOWER(COALESCE(s.remark,'')) REGEXP 'pending|belum|unpaid' THEN 0 ELSE 1 END,
                   l.status_aktif_site,s.created_at,s.updated_at
            FROM status_pembayaran s JOIN listrik_pln l ON l.id=s.listrik_pln_id WHERE s.is_operational=1
            SQL);

        $months = ['jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'ags', 'sep', 'okt', 'nov', 'des'];
        $totals = $fallback = $values = [];
        foreach ($months as $index => $month) {
            $number = $index + 1;
            $totals[] = "SUM(CASE WHEN bulan={$number} THEN jumlah_tagihan ELSE 0 END) AS {$month}, COUNT(CASE WHEN bulan={$number} THEN 1 END) AS {$month}_count";
            $fallback[] = "SUM({$month}) AS {$month}";
            $values[] = "COALESCE(CASE WHEN p.{$month}_count>0 THEN p.{$month} ELSE a.{$month} END,0) AS {$month}";
        }
        DB::statement('CREATE OR REPLACE VIEW inbuilding_all_effective AS WITH p AS (
            SELECT UPPER(TRIM(site_id)) AS code, tahun, MAX(id) AS id, MAX(site_name) AS site_name,
                   MAX(nama_bm) AS nama_bm, MAX(created_at) AS created_at, MAX(updated_at) AS updated_at,'.implode(',', $totals).'
            FROM payment_ibc_effective GROUP BY UPPER(TRIM(site_id)),tahun
        ), a AS (
            SELECT UPPER(TRIM(site_id)) AS code,tahun,MAX(id) AS id,MAX(site_name) AS site_name,MAX(status) AS status,
                   MAX(nama_bm) AS nama_bm,MAX(tp_nontp) AS tp_nontp,MAX(created_at) AS created_at,MAX(updated_at) AS updated_at,'.implode(',', $fallback).'
            FROM inbuilding_all GROUP BY UPPER(TRIM(site_id)),tahun
        ), k AS (SELECT code,tahun FROM p UNION SELECT code,tahun FROM a)
        SELECT COALESCE(a.id,p.id) AS id,k.code AS site_id,COALESCE(p.site_name,a.site_name) AS site_name,a.status,
               COALESCE(p.nama_bm,a.nama_bm) AS nama_bm,a.tp_nontp,k.tahun,'.implode(',', $values).',
               COALESCE(p.created_at,a.created_at) AS created_at,COALESCE(p.updated_at,a.updated_at) AS updated_at
        FROM k LEFT JOIN p ON p.code=k.code AND p.tahun=k.tahun LEFT JOIN a ON a.code=k.code AND a.tahun=k.tahun');

        foreach (['pln' => ['payment_pln_effective', 'harga'], 'inbuilding' => ['payment_ibc_effective', 'jumlah_tagihan']] as $scope => [$table,$amount]) {
            $account = $scope === 'pln' ? ',UPPER(TRIM(id_pelanggan)) AS account' : '';
            $group = $scope === 'pln' ? ',UPPER(TRIM(id_pelanggan))' : '';
            $partition = $scope === 'pln' ? 'code,account' : 'code';
            $columns = $scope === 'pln'
                ? '-ABS(c.id) AS id,c.account AS id_pelanggan,c.code AS site_id,c.site_name,c.bulan,c.tahun,c.previous_amount AS tagihan_sebelumnya,c.amount AS tagihan_saat_ini,c.amount-c.previous_amount AS selisih,ROUND((c.amount-c.previous_amount)*100/c.previous_amount,2) AS kenaikan_persen,c.created_at,c.updated_at'
                : "-ABS(c.id) AS id,c.code AS site_id,CONCAT(FLOOR((c.previous_period-1)/12),'-',LPAD(MOD(c.previous_period-1,12)+1,2,'0')) AS periode_sebelumnya,c.previous_amount AS tagihan_sebelumnya,CONCAT(c.tahun,'-',LPAD(c.bulan,2,'0')) AS periode_saat_ini,c.amount AS tagihan_saat_ini,c.amount-c.previous_amount AS selisih,ROUND((c.amount-c.previous_amount)*100/c.previous_amount,2) AS kenaikan_persen,c.created_at,c.updated_at";
            $legacyMatch = $scope === 'pln'
                ? 'c.tahun=a.tahun AND c.bulan=a.bulan AND c.account=UPPER(TRIM(a.id_pelanggan))'
                : "CONCAT(c.tahun,'-',LPAD(c.bulan,2,'0'))=a.periode_saat_ini";
            DB::statement("CREATE OR REPLACE VIEW anomali_tagihan_{$scope}_effective AS WITH m AS (
                SELECT UPPER(TRIM(site_id)) AS code{$account},tahun,bulan,MAX(id) AS id,MAX(site_name) AS site_name,
                       SUM({$amount}) AS amount,MAX(created_at) AS created_at,MAX(updated_at) AS updated_at
                FROM {$table} WHERE tahun IS NOT NULL AND bulan BETWEEN 1 AND 12 GROUP BY UPPER(TRIM(site_id)),tahun,bulan{$group}
            ), sequenced AS (
                SELECT m.*,LAG(amount) OVER w AS previous_amount,LAG(tahun*12+bulan) OVER w AS previous_period
                FROM m WINDOW w AS (PARTITION BY {$partition} ORDER BY tahun,bulan)
            ) SELECT {$columns} FROM sequenced c
              WHERE c.previous_period=c.tahun*12+c.bulan-1 AND c.previous_amount>0 AND c.amount>c.previous_amount
            UNION ALL SELECT a.* FROM anomali_tagihan_{$scope} a WHERE NOT EXISTS (
                SELECT 1 FROM sequenced c
                WHERE c.code=UPPER(TRIM(a.site_id)) AND {$legacyMatch}
                  AND c.previous_period=c.tahun*12+c.bulan-1
            )");
        }
    }

    public static function drop(): void
    {
        foreach (array_reverse(self::VIEWS) as $view) {
            DB::statement('DROP VIEW IF EXISTS '.$view);
        }
    }
}
