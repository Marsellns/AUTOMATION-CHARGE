# Equipment Relocation

Inventaris Equipment Relocation disimpan pada tabel MySQL
`equipment_relocation_inventory`. Halaman memuat inventaris melalui endpoint
Laravel `/equipment-relocation/inventory-data` dan menggabungkannya dengan
perubahan relokasi dari tabel `equipment_relocations`. Setelah data diimpor,
halaman tidak membaca JSON atau file Excel saat berjalan. Perubahan inventaris
di MySQL terlihat setelah halaman dimuat ulang.

Kode modul dikelompokkan berdasarkan domain: controller berada di
`app/Http/Controllers/EquipmentRelocationController.php`, tampilan berada di
`resources/views/equipment-relocation/`, dan aset tampilan berada di
`public/assets/equipment-relocation/`.

Snapshot untuk impor disimpan di
`storage/app/imports/equipment_relocation_inventory.json`. Berkas sumber lama
diarsipkan secara privat di `storage/app/imports/sources/`. Untuk memasukkan
snapshot ke MySQL, jalankan:

```powershell
.\sail.bat artisan migrate --path=database/migrations/2026_10_01_000000_create_equipment_relocation_inventory_table.php
.\sail.bat artisan equipment:import-inventory
```

Jika tabel sudah berisi inventaris dan sumber baru memang menggantikannya,
gunakan `equipment:import-inventory --replace`. Impor memvalidasi urutan kolom,
kunci unik, dan jumlah baris sebelum transaksi selesai. Tabel relokasi tidak
dihapus ketika inventaris diganti.

Untuk menyiapkan snapshot baru dari file acuan mentah:

```powershell
node scripts/build-equipment-relocation-inventory.cjs "C:\\path\\ke\\equipment_inventory.json"
```

Script menulis snapshot ke `storage/app/imports/`, bukan `public/data/`.
Jalankan kembali perintah impor setelah file disiapkan. Folder `public/data/`
tidak lagi menyimpan berkas inventaris Equipment Relocation. Berkas sumber di
penyimpanan privat hanya dipakai untuk menyiapkan impor ulang.

Sumber inventaris lama berisi 50.640 unit: 37.532 RU dan 13.108 BBP. Kriteria
`Safe to Reloc` mengikuti sumber: `Safe` untuk RU dan `OK` untuk BBP. Total
safe adalah 2.811 unit (470 RU dan 2.341 BBP). Diagram NOP dan tipe equipment
menghitung seluruh baris inventaris MySQL sesuai filter, sedangkan diagram progres
menghitung status monitoring di tabel relokasi. Baris tanpa progres
ditampilkan sebagai `Belum Diisi`.

Alur Document Circulation di aplikasi aktif adalah Uploaded → Manager NOP →
Manager SQ → Manager NOS → Manager NBAE. Data dokumen dan riwayat approval
disimpan di database aplikasi; PDF contoh tanpa metadata tidak dibuat otomatis
sebagai dokumen Presales.
