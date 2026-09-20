# Migration naming

Nama file migration Laravel sengaja mempertahankan prefix tanggal dan waktu.
Prefix tersebut menentukan urutan eksekusi dan menjadi identitas migration yang
tersimpan di tabel `migrations`. Karena migration di project ini sudah pernah
dijalankan, file tidak boleh diganti nama tanpa prosedur migrasi database yang
terencana.

Berikut pemetaan nama teknis ke nama yang lebih mudah dipahami:

| Nama file saat ini | Nama yang disarankan / tujuan |
| --- | --- |
| `0001_01_01_000000_create_users_table.php` | `create_users_authentication_tables` |
| `0001_01_01_000001_create_cache_table.php` | `create_cache_tables` |
| `0001_01_01_000002_create_jobs_table.php` | `create_queue_job_tables` |
| `2026_08_19_052315_create_regions_table.php` | `create_regions_table` |
| `2026_08_19_052324_create_sites_table.php` | `create_sites_table` |
| `2026_08_19_052824_create_site_monthly_metrics_table.php` | `create_site_monthly_metrics_table` |
| `2026_08_19_074634_create_activity_log_table.php` | `create_activity_log_table` |
| `2026_08_19_104223_create_permission_tables.php` | `create_permission_tables` |
| `2026_08_19_104500_add_is_anomaly_to_site_monthly_metrics_table.php` | `add_anomaly_flag_to_site_monthly_metrics` |
| `2026_08_19_120000_create_po_hq_table.php` | `create_po_hq_table` |
| `2026_08_20_010000_create_dataset_tables.php` | `create_simawar_dataset_tables` |
| `2026_08_26_010000_infrastruktur_management_v2.php` | `update_infrastructure_management_tables` |
| `2026_08_27_010000_create_electricity_tables.php` | `create_electricity_management_tables` |
| `2026_09_01_000000_flag_cost_sentinel_as_site_metric_anomaly.php` | `flag_financial_sentinel_anomalies` |

Untuk migration baru, gunakan format:

```text
YYYY_MM_DD_HHMMSS_descriptive_action.php
```

Contoh:

```text
2026_09_03_150000_add_invoice_status_to_payment_pln.php
```
