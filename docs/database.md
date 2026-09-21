# Database SIMASTER

Dokumen ini adalah panduan operasional database untuk developer atau admin yang meneruskan project SIMASTER.

## 1. Arsitektur singkat

Pada development, aplikasi berjalan di Docker Compose:

- `laravel.test`: PHP/Laravel dan Artisan.
- `mysql`: MySQL 8.4.
- Volume `sail-mysql`: penyimpanan data MySQL yang persisten.
- `.env`: menentukan koneksi aplikasi melalui `DB_*`.

Aplikasi membaca koneksi default dari `DB_CONNECTION`. Pada Docker development, gunakan MySQL dengan host `mysql`, bukan `localhost` dari dalam container.

Halaman website tidak membaca file Excel atau folder `DATASET` saat request berjalan. Semua dashboard, tabel, filter, chart, dan export membaca tabel MySQL. Folder `DATASET` hanya menjadi sumber opsional untuk command import/rebuild; setelah data diimpor, folder tersebut boleh tidak tersedia tanpa mengganggu website.

Untuk DBeaver dari Windows, buat koneksi MySQL ke `localhost:3306` (atau nilai
`FORWARD_DB_PORT` pada `.env`), dengan database `DB_DATABASE`. Host `mysql` hanya
dipakai oleh aplikasi di dalam jaringan Docker.

Perintah Windows dapat dijalankan melalui wrapper:

```powershell
.\sail.bat artisan migrate:status
.\sail.bat artisan db:show
```

Atau langsung:

```powershell
docker compose exec laravel.test php artisan migrate:status
docker compose exec laravel.test php artisan db:show
docker compose exec mysql mysql -u<DB_USERNAME> -p<DB_PASSWORD> <DB_DATABASE>
```

Jangan menaruh password database ke repository. Gunakan `.env` lokal dan `.env.example` sebagai template.

## 2. Sumber kebenaran schema

Schema database dikelola oleh file di `database/migrations/`. Laravel mencatat migration yang sudah dijalankan pada tabel `migrations`.

Perintah utama:

```powershell
.\sail.bat artisan migrate:status
.\sail.bat artisan migrate
.\sail.bat artisan migrate:rollback
```

Aturan perubahan schema:

1. Jangan mengubah migration lama yang sudah pernah dijalankan di environment bersama.
2. Buat migration baru dengan nama deskriptif, misalnya `add_status_to_payment_pln`.
3. Jalankan `migrate` dan uji query terkait.
4. Commit migration bersama perubahan model, import, controller, atau export yang membutuhkannya.
5. Untuk perubahan berisiko, backup database sebelum migration.

`migrate:fresh` menghapus seluruh tabel dan data. Gunakan hanya untuk database development yang memang boleh di-reset.

```powershell
.\sail.bat artisan migrate:fresh --seed
```

## 3. Kelompok tabel utama

### P&L dan master site

- `regions`: kode wilayah dari prefix Site ID.
- `sites`: master site dari Dapot.
- `site_monthly_metrics`: revenue, cost, dan profit/loss per site, bulan, dan tahun.
- `site_owners`: data Dapot/ANT dan klasifikasi NOP/site owner.

Relasi penting: `site_monthly_metrics.site_id` menunjuk ke `sites.id`; pencocokan `site_owners` dengan `sites` menggunakan `site_code` dan `site_id` yang dinormalisasi.

### Dataset dan infrastruktur

Tabel seperti `sewa_lahan_renewals`, `combat_sites`, `recurring_ipas`, `recurring_tagihan_ipas`, `data_asset_towers`, dan `data_site_unlocks` menyimpan snapshot dari file dataset masing-masing.

### Electricity

Tabel utama berada di migration `create_electricity_tables`, termasuk `listrik_pln`, `payment_pln`, `anomali_tagihan_pln`, `listrik_all`, dan tabel inbuilding.

### Operasional Laravel

- `users`, `roles`, dan tabel permission: login dan hak akses.
- `sessions`: session database.
- `cache`: cache database jika `CACHE_STORE=database`.
- `jobs`, `failed_jobs`, `job_batches`: queue database jika `QUEUE_CONNECTION=database`.
- `activity_log`: audit perubahan data.

## 4. Urutan setup database baru

1. Salin `.env.example` menjadi `.env` dan isi `DB_*`.
2. Jalankan container:

```powershell
docker compose up -d
```

3. Pasang dependency dan migration:

```powershell
.\sail.bat composer install
.\sail.bat artisan key:generate
.\sail.bat artisan migrate --seed
```

4. Pastikan migration selesai:

```powershell
.\sail.bat artisan migrate:status
.\sail.bat artisan db:show
```

5. Import data sumber sesuai kebutuhan melalui halaman upload aplikasi. Untuk
   command khusus PnL, tempatkan file sementara di `storage/app/imports/`; jangan
   simpan folder Excel sumber di root proyek.

## 5. Import data

Lihat daftar command lengkap:

```powershell
.\sail.bat artisan list
```

Command yang paling penting:

```powershell
.\sail.bat artisan simawar:import-pnl
.\sail.bat artisan simawar:import-pnl --fresh
.\sail.bat artisan dataset:import-all
```

Sebelum import, cek heading file Excel bila tersedia:

```powershell
.\sail.bat artisan simawar:check-headings
```

### Peringatan `--fresh`

`simawar:import-pnl --fresh` menghapus data `site_monthly_metrics`, `sites`, dan `regions` sebelum mengisi ulang. Gunakan hanya setelah backup dan setelah memastikan file Dapot serta file P&L yang benar tersedia.

Import dataset snapshot dapat menghapus isi tabel target sebelum mengisi ulang. Selalu baca output command dan simpan log import.

Setelah import P&L:

```powershell
.\sail.bat artisan migrate:status
.\sail.bat artisan tinker
```

Contoh pemeriksaan melalui Tinker:

```php
App\Models\Site::count();
App\Models\SiteMonthlyMetric::count();
App\Models\SiteMonthlyMetric::where('tahun', 2026)->count();
```

## 6. Backup dan restore

Backup seluruh database ke file lokal. Ganti placeholder sesuai nilai di `.env`:

```powershell
$backup = "storage/app/backups/simaster_$(Get-Date -Format yyyyMMdd_HHmmss).sql"
$dbUser = 'isi_dari_DB_USERNAME'
$dbPassword = 'isi_dari_DB_PASSWORD'
$dbName = 'isi_dari_DB_DATABASE'
New-Item -ItemType Directory -Force (Split-Path $backup) | Out-Null
docker compose exec -T mysql mysqldump -u$dbUser -p$dbPassword $dbName > $backup
```

Jangan memasukkan password atau file backup berisi data sensitif ke git.

Restore backup ke database development:

```powershell
$dbUser = 'isi_dari_DB_USERNAME'
$dbPassword = 'isi_dari_DB_PASSWORD'
$dbName = 'isi_dari_DB_DATABASE'
docker compose exec -T mysql mysql -u$dbUser -p$dbPassword $dbName < storage/app/backups/simaster_YYYYMMDD_HHMMSS.sql
```

Restore menimpa data yang ada. Pastikan target database benar sebelum menjalankan.

## 7. Verifikasi setelah perubahan

Jalankan minimal:

```powershell
.\sail.bat artisan migrate:status
.\sail.bat artisan test
.\sail.bat artisan view:cache
```

Untuk perubahan import, periksa jumlah baris dan periode:

```powershell
.\sail.bat artisan tinker
```

Jangan hanya memeriksa apakah command selesai. Bandingkan jumlah site, jumlah metric, tahun/bulan terbaru, dan beberapa Site ID dengan file sumber.

## 8. Checklist penerus proyek

- Pastikan `docker compose ps` menunjukkan `laravel.test` dan `mysql` sehat.
- Pastikan `.env` menunjuk ke database yang benar.
- Jangan menjalankan `migrate:fresh` atau import `--fresh` tanpa backup.
- Perubahan tabel selalu dibuat sebagai migration baru.
- File sumber Excel dicatat nama, periode, dan tanggal importnya.
- Setelah import, cek jumlah data dan dashboard.
- Backup sebelum migration besar atau import ulang.
- Jangan commit `.env`, password, dump produksi, atau file backup berisi data sensitif.
