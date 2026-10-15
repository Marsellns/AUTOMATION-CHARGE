# Sinkronisasi Rebuild Sewa Lahan dan Combat

Sumber aktif adalah `DATASET/02 Infrastruktur management/Sewa Lahan & Combat/Rebuild Database Combat dan Sewa Lahan(AutoRecovered) (1).xlsm`.

- Sewa Lahan menggunakan **DATA SEWA LAHAN** saja, sesuai cakupan yang dipilih: 45 transaksi dan 42 Site ID unik pada workbook saat ini.
- Combat menggunakan **DATABASE COMBAT**: 134 catatan dari 76 Site ID unik. Setiap catatan tahun/kontrak disimpan, sehingga histori tidak hilang.
- Sheet `DATABASE RENEWAL`, backup, NOTE, Pivot, dan dashboard Excel tidak ditambahkan sebagai transaksi.
- Semua kolom asli, nomor baris, nama sheet, serta SHA-256 workbook disimpan pada `source_details._source`. Header ganda menggunakan nilai nonkosong pertama.
- Kolom tahun, status, kontrak, tanggal, dan nominal dipetakan ke tabel aplikasi yang sudah ada. Nilai waktu tanpa tanggal seperti `00:00:00` tidak diubah menjadi tanggal hari ini.
- Diagram dan tabel drill-down tetap memakai pemilihan Site ID kanonis yang sama. Kartu Total Site menunjukkan jumlah catatan; keterangan di bawahnya menunjukkan jumlah site unik.
- Label pemilik dari workbook tetap menjadi sumber utama; master Site Owner menjadi fallback bila label kosong. Site yang belum dapat dipetakan ditampilkan pada kategori tersendiri, bukan dipaksa menjadi TP/Telkomsel.

## Menjalankan sinkronisasi

```bash
php artisan dataset:import-infrastructure-rebuild --dry-run
php artisan dataset:import-infrastructure-rebuild
php artisan simaster:database-status
```

Untuk memperbarui hanya satu tabel, tambahkan `--only=sewa-lahan` atau `--only=combat`. `--file=...` dapat digunakan untuk workbook pengganti dengan kedua nama sheet yang sama. Perintah `dataset:import-all --only=sewa-lahan` dan `--only=combat` juga memakai importer ini.

Seluruh baris sumber dibaca dan divalidasi sebelum database diubah. Snapshot sebelumnya dicadangkan ke `storage/app/backups/infrastructure-*.json`, lalu penggantian kedua tabel dilakukan dalam satu transaksi MySQL. Kegagalan baca/insert membatalkan perubahan. Sinkronisasi tidak mengirim email atau notifikasi.

## Monitoring melalui DBeaver

DBeaver adalah klien untuk server MySQL aplikasi. Pada lingkungan pengembangan yang diperiksa, koneksinya adalah `127.0.0.1:3306`, database `simawar_dashboard`; aplikasi Docker mengakses server yang sama melalui host `mysql`.

Refresh tabel atau jalankan ulang SQL di DBeaver setelah sinkronisasi:

```sql
SELECT COUNT(*) AS transaksi, COUNT(DISTINCT site_code) AS site_unik
FROM sewa_lahan_renewals WHERE deleted_at IS NULL;

SELECT COUNT(*) AS catatan, COUNT(DISTINCT site_code) AS site_unik
FROM combat_sites WHERE deleted_at IS NULL;

SELECT site_code, tahun_justi_dirnet, status_perpanjangan,
       JSON_UNQUOTE(JSON_EXTRACT(source_details, '$._source.sheet')) AS sheet_sumber,
       JSON_EXTRACT(source_details, '$._source.row') AS baris_excel
FROM combat_sites WHERE deleted_at IS NULL
ORDER BY site_code, tahun_justi_dirnet;
```

`simaster:database-status` memeriksa tabel/view seluruh modul: P&L, Infrastruktur, Electricity Centralized/Inbuilding, PO, Equipment, dan Feasibility Site. Tabel alur input seperti `po_varcost`, `equipment_relocations`, `upload_files`, dan `bongkar_rampung_pln` dapat kosong jika belum ada transaksi/input sumber. Status kosong tidak berarti datanya disimpan di browser.

Push GitHub hanya mengirim kode. Workbook, `.env`, dan data MySQL tidak masuk Git. Di cPanel, sediakan workbook secara privat dan jalankan perintah sinkronisasi dengan PHP hosting yang sesuai, atau pindahkan backup MySQL melalui prosedur deployment yang sudah ada.
