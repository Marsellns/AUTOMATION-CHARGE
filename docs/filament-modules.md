# Filament per modul SIMASTER

SIMASTER menggunakan satu panel Filament dengan ID `report`. Setiap kelompok modul memiliki plugin, dan setiap halaman/submodul operasional memiliki custom Page Filament sendiri. Page menjalankan controller Laravel yang sudah ada dan merender Blade yang sama di dalam layout Filament. DataTables, grafik, form, impor/ekspor, dan akses dokumen tetap berasal dari implementasi operasional tersebut.

## Lokasi modul

Semua definisi berada di `app/Filament/Modules/`:

- `Dashboard/`: Dashboard Utama dan Profit & Loss.
- `Infrastructure/`: dashboard Infrastruktur, Sewa Lahan, Site Telkomsel, Site TP, Combat, kedua Recurring, Jaknet & Dapot, Site Unlock, BAPSS, dan Upload PDF.
- `Electricity/`: dashboard Electricity, Centralized, Inbuilding, dan seluruh submodulnya.
- `PoMonitoring/`: PO Varcost, PO HQ, dan Presales.
- `DataPotensi/`: Site Owner, Data Site, Asset Tower, dan Search All Resource.
- `EquipmentRelocation/`: inventaris dan progres relokasi.
- `Accounts/`: pendaftaran halaman persetujuan akun yang sudah ada dan Page notifikasi.

Setiap direktori mempunyai kelas `*Plugin.php` yang mendaftarkan Page miliknya. Kelas Page berada di subdirektori `Pages/` dan menentukan pola nama route serta definisi menu. Site TP dan Site Telkomsel memakai Page yang berbeda dengan controller Sewa Lahan yang sama; pemilihan memakai query `ownership_scope`. Route upload Infrastruktur memilih Page berdasarkan parameter `dataset`.

## Pendaftaran dan rendering

`ReportModuleRegistry.php` menentukan urutan plugin dan memilih Page dari route serta query. `ModuleNavigation.php` menyusun menu bertingkat dari definisi Page. `ReportPanelProvider` memasang plugin ke panel yang sama. API lama `ReportModules::navigation()` dan `dashboardGroups()` tetap tersedia sebagai facade.

Middleware `RenderReportInFilament` menjalankan controller, binding, validasi, dan middleware route, lalu memilih Page modul untuk respons View yang berhasil. Endpoint JSON, ekspor, dan download tetap ditangani Laravel. Custom Page terdaftar pada panel tanpa menambahkan URL baru, sehingga nama route, URL, metode HTTP, dan aturan akses tetap berasal dari `routes/web.php`.

`ModuleReportPage` memakai renderer `OperationalReport` yang sudah ada. Saat Livewire refresh, identitas route, parameter model, dan query tetap dikunci; Page memeriksa kembali bahwa route milik modul tersebut. Alias Livewire `simaster.modules.*` didaftarkan oleh plugin. Alias renderer lama dipertahankan untuk halaman yang terbuka sebelum rilis dan untuk kompatibilitas route di luar registry.

## Menambah atau memperbarui modul

Ubah Page modul untuk metadata menu dan pola route. Tambahkan Page ke metode `pages()` pada plugin kelompoknya. Untuk kelompok baru, daftarkan plugin pada `ReportModuleRegistry::plugins()`. Perubahan form, grafik, tabel, atau aturan bisnis dilakukan pada controller, view, service, dan model modul seperti sebelumnya.

Definisi menu menyimpan label, nama route, pola aktif, ikon, query opsional, dan induk menu. Urutan Page dalam plugin dan urutan plugin dalam registry menentukan urutan sidebar. Endpoint baru harus tetap memiliki otorisasi pada route/controller; pemetaan Page tidak menggantikan otorisasi.

## Deploy dan verifikasi

Rilis pertama pemisahan ini mencakup folder modul, registry, renderer, provider panel, facade, serta middleware yang memilih Page. Setelah itu perubahan metadata Page dapat dibatasi ke modul terkait. Aplikasi tetap memakai satu runtime Laravel dan masih mempunyai dependensi bersama seperti route, layout, aset build, Composer, serta database.

Segarkan cache komponen Filament saat rilis agar daftar Page mengikuti kode yang terpasang. Pemisahan ini tidak membutuhkan migrasi database atau dependency Composer baru.

Tes `FilamentReportPanelTest` memeriksa rendering seluruh menu, Page tiap modul, struktur sidebar, hak akses, notifikasi, respons JSON, model binding, parameter periode, refresh upload per dataset, dan properti Livewire yang dikunci. Tes Equipment Relocation, Presales, BAPSS, dan template upload memeriksa alur bisnis yang digunakan Page terkait.
