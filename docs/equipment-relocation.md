# Equipment Relocation

Inventaris Equipment Relocation adalah snapshot mandiri di
`public/data/equipment_relocation_inventory.json`. Halaman membaca snapshot
tersebut dan menggabungkannya dengan perubahan relokasi di tabel
`equipment_relocations`. Aplikasi tidak bergantung pada folder sumber
inventaris saat berjalan.

Untuk memperbarui inventaris dari file acuan:

```powershell
node scripts/build-equipment-relocation-inventory.cjs "C:\\path\\ke\\equipment_inventory.json"
```

Snapshot saat ini berisi 50.640 unit: 37.532 RU dan 13.108 BBP. Kriteria
`Safe to Reloc` mengikuti sumber: `Safe` untuk RU dan `OK` untuk BBP. Total
safe adalah 2.811 unit (470 RU dan 2.341 BBP). Diagram NOP dan tipe equipment
menghitung seluruh baris inventaris sesuai filter, sedangkan diagram progres
menghitung status monitoring yang tersimpan di database. Baris tanpa progres
ditampilkan sebagai `Belum Diisi`.

Alur Document Circulation di aplikasi aktif adalah Uploaded → Manager NOP →
Manager SQ → Manager NOS → Manager NBAE. Data dokumen dan riwayat approval
disimpan di database aplikasi; PDF contoh tanpa metadata tidak dibuat otomatis
sebagai dokumen Presales.
