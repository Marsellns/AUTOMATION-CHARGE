# Data Listrik All dan notifikasi operasional

## Pemeriksaan sumber 2 Oktober 2026

Sumber: `DATASET/03 Electricity/Centralized/Listrik All/Data Master PLN Centralized_Flagging September 2026_Eastern Jabotabek.xlsx`, sheet `Data Master`, header baris 3 dan data baris 4–4030.

- Terdapat 4.027 rekening PLN dan 4.023 Site ID unik. Beberapa site memiliki lebih dari satu rekening; jumlah rekening dan site tidak boleh dipertukarkan.
- Flagging positif Januari–Agustus 2026 mencakup berturut-turut 3.872, 3.882, 3.904, 3.918, 3.942, 3.951, 3.983, dan 4.021 site unik. Angka ini sama dengan database dan diagram sebelum perbaikan.
- Persentase diagram membagi jumlah site yang memiliki flagging positif dengan populasi site pada snapshot, dengan mengecualikan status tidak aktif yang tersedia. Ini adalah cakupan flagging pada snapshot, bukan pengukuran akurasi data atau pelunasan semua rekening per site.
- Master `Listrik PLN/Data Master Export.xlsx` menyatakan seluruh 4.042 barisnya Active/active. File ini tidak memberikan riwayat populasi aktif per bulan untuk menghitung tingkat pembayaran historis yang berbeda.
- `Inquiry` adalah tagihan; `Flagging` digunakan sebagai realisasi pembayaran. September 2026 hanya memiliki `Inquiry September 20262` di kolom BW, tanpa Flagging September. Oktober–Desember belum mempunyai kedua kolom tersebut. Tidak adanya kolom pembayaran tidak berarti tidak ada pembayaran.
- Ada nilai kosong dan error Excel seperti `#N/A` pada beberapa kolom sumber. Nilai tersebut tidak dianggap bukti pembayaran positif. Verifikasi status pelunasan tetap memerlukan sumber transaksi/konfirmasi operasional.

Angka diagram referensi kedua tidak sama dengan agregat Flagging 2026 dari workbook ini. Tahun, arti warna, cakupan site, dan sumber populasi per bulan diperlukan sebelum membandingkan persentasenya. Jangan menyalin angka referensi ke database tanpa rekonsiliasi sumber.

## Alur impor dan tampilan

Unggahan website memakai `CentralizedListrikAllImport` untuk memperbarui `listrik_all` saja. Command `dataset:import-centralized-submodules` mengimpor Listrik All bersama submodul Centralized lain, sedangkan `dataset:import-payment-pln-master` mengisi `payment_pln_master_monthly` secara terpisah dari workbook sumber. Kedua tabel tidak saling diganti oleh impor yang berbeda. Import website membaca kolom Flagging (termasuk variasi nama bulan Indonesia/Inggris), mempertahankan rekening per Site ID, dan menolak file tanpa data. Transaksi unggahan menjaga snapshot sebelumnya bila proses gagal.

Tabel Listrik All memakai nilai master pembayaran sesuai pasangan Site ID dan ID Pelanggan bila tersedia. Ini mencegah total pembayaran satu site diulang untuk setiap rekening. Ekspor tetap mengikuti kontrak sebelumnya: agregat per site dari master pembayaran bila tersedia, atau data Listrik All bila master tidak tersedia. Diagram cakupan menampilkan periode tanpa data sebagai tidak tersedia, bukan 0%.

## Notifikasi pagi dan sore

- Waktu: pukul 08.00 dan 17.00 WIB setiap hari (`Asia/Jakarta`). Pengaturan: `NOTIFICATION_DAILY_AT`, `NOTIFICATION_EVENING_AT`, dan `NOTIFICATION_TIMEZONE`.
- Kanal website: pengguna dengan akun approved. Kanal email: alamat tujuan alert yang dikonfigurasi.
- Anomali listrik dan peringatan Infrastruktur menggunakan tujuan email yang sudah ada. Site Loss juga mendapat email lampiran Excel; `SITE_LOSS_ALERT_EMAIL` dapat diisi untuk tujuan khusus, dengan fallback ke email alert Infrastruktur/Electricity.
- Pengiriman dilakukan hanya jika terdapat kondisi peringatan. Setiap topik dan kanal dibatasi satu pengiriman per slot pagi/sore; pengiriman pagi tidak menghalangi sore. Slot yang gagal dikirim dapat dicoba ulang.
- Email Centralized dan Inbuilding melampirkan hanya baris anomali kenaikan tagihan >50% dari seluruh periode yang tersedia, beserta tautan ke tabel anomali sumbernya. Email Infrastruktur dipisah menurut Sewa Lahan, Site TP, Site Telkomsel, dan Combat; tiap lampiran hanya berisi site yang perlu perhatian sesuai kategori dan tautannya membuka filter peringatan. Email Site Loss memakai periode P&L terbaru, mengecualikan data bertanda anomali, dan menyertakan tautan ke periode yang sama.
- Impor sebelum pukul 08.00 tidak mengambil slot pagi. Pemanggilan manual/import sesudah waktu jadwal menggunakan slot terakhir yang sudah jatuh tempo.
- `docker/supervisord.conf` menjalankan scheduler bersama aplikasi dan dipasang melalui Compose supaya konfigurasi tetap tersedia ketika container dibuat ulang. Container/aplikasi harus tetap hidup pada waktu pengiriman.

Pemeriksaan tanpa mengirim notifikasi: `docker compose exec -T laravel.test php artisan schedule:list`. Uji pengiriman menggunakan transport email `array` pada database testing; keberhasilan pengujian tidak membuktikan penerimaan email oleh server SMTP tujuan.
