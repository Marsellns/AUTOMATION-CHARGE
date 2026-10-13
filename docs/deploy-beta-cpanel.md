# Deployment beta.simawar.web.id melalui cPanel Git

Checkout cPanel berada di `/home3/simawarw/beta.simawar.web.id`. Document Root subdomain harus `/home3/simawarw/beta.simawar.web.id/public`; pengaturan ini sudah berhasil diubah melalui **Domains → Manage**. Akar repository, `.env`, `storage`, dan dependency tetap berada di luar Document Root.

## Mengapa tombol Deploy belum bisa dipakai

cPanel mensyaratkan `.cpanel.yml` yang sudah **committed** di branch yang sedang di-checkout, serta checkout yang bersih. `Update from Remote` hanya mengambil commit baru dari GitHub; tombol **Deploy HEAD Commit** menggunakan `.cpanel.yml`. Saat pemeriksaan awal, GitHub `main` masih pada `43affc020ee25d378c483d37f8dfedd0c8daa849` dan belum memiliki `.cpanel.yml`. File yang disiapkan di komputer pengembangan belum ada di hosting sampai commit dan push ke GitHub, kemudian **Update from Remote** dijalankan.

Hosting ini mempunyai PHP CLI 8.2 di `/opt/cpanel/ea-php82/root/usr/bin/php`, tetapi `php` default adalah 8.0 dan Composer, Node, serta npm tidak tersedia. Karena itu GitHub Actions membangun dependency dan aset terlebih dahulu, lalu menerbitkan paket ZIP untuk SHA commit yang tepat sebagai GitHub prerelease `beta-<SHA>`. Paket ini tidak memuat `.env`, database, atau dokumen operasional. cPanel mengunduh paket untuk HEAD yang sama dan memasang hanya runtime hasil build ke checkout Git. `curl` dan `unzip` sudah tersedia di hosting. Workflow produksi `simawars.web.id` tetap terpisah.

## Sebelum deploy pertama

1. Pastikan `beta.simawar.web.id` memakai **PHP web 8.2 atau lebih baru**. PHP CLI 8.2 tidak membuktikan versi web. Fitur `multiphp` akun saat ini ditolak oleh UAPI dengan pesan `You do not have the feature “multiphp”`; bila pemeriksaan deploy melaporkan PHP web lama, minta Jagoan Hosting mengaktifkan/mengatur PHP 8.2+ untuk subdomain beta saja. Skrip memeriksa dengan berkas PHP sementara yang segera dihapus.
2. Buat database MySQL dan user di cPanel. Jika memindahkan database dan dokumen lama, lakukan backup dan pemulihan sesuai [panduan cPanel utama](deploy-cpanel.md). Jangan menjalankan migrasi otomatis pada database berisi data tanpa backup dan tinjauan skema.
3. Buat `.env` privat di akar checkout server, mengacu pada `.env.cpanel.example`. Set `APP_NAME=SIMAWAR`, `APP_URL=https://beta.simawar.web.id`, `APP_ENV=production`, `APP_DEBUG=false`, APP_KEY yang sesuai, koneksi MySQL, dan SMTP. Pertahankan APP_KEY lama bila data lama dipindahkan. Jangan commit `.env`, atau mengirim password lewat chat. Batasi akses file (`chmod 600 .env`).
4. Pastikan direktori `storage/` berisi data yang memang diperlukan dan writable oleh PHP hosting. Preflight menuntut tabel migrasi dan tidak adanya migrasi tertunda. Jalankan migrasi yang telah ditinjau secara terpisah menggunakan PHP CLI 8.2.
5. Jika sudah ada `vendor` atau direktori aset hasil build di checkout cPanel, tinjau isinya dan pindahkan secara sadar ke lokasi backup pribadi sebelum deploy. Skrip menolak menimpa direktori yang sudah ada.

## Memperbarui beta

1. Commit dan push perubahan ke GitHub `main`. Tunggu GitHub Actions **Build SIMAWAR beta for cPanel Git** berhasil dan prerelease `beta-<SHA>` berisi ZIP serta `.sha256`. Paket dibangun dari commit yang sama dengan source Git.
2. Di cPanel **Git Version Control**, klik **Update from Remote**. Periksa HEAD berubah ke SHA commit tersebut dan checkout bersih.
3. Klik **Deploy HEAD Commit**. `.cpanel.yml` menjalankan `scripts/cpanel-deploy-beta.sh`. Skrip memeriksa Document Root dan PHP web melalui URL HTTPS sementara, memakai PHP CLI 8.2, mengunduh paket dengan checksum, memeriksa `RELEASE.json` dan `composer.lock`, menjalankan preflight database, lalu mengaktifkan runtime yang cocok dengan commit. Pesan gagal diawali `SIMAWAR beta deployment stopped:`.
4. Verifikasi `/login`, CSS/JS, login dan logout, serta fungsi yang memerlukan database dan notifikasi. Pastikan cron scheduler dan SMTP dikonfigurasi sesuai kebutuhan; deploy tidak membuat cron atau mengirim email pengujian.

Paket runtime lama disimpan di `~/simawar-beta-deploy/releases/<SHA>`. Source tetap berasal dari checkout Git. Untuk kembali ke kode sebelumnya, revert commit di GitHub lalu ulangi **Update from Remote** dan **Deploy HEAD Commit**; paket untuk SHA revert dibangun oleh workflow. Pemulihan database/dokumen dilakukan terpisah. cPanel menarik source sebelum task deploy berjalan, sehingga selama pembaruan beta mungkin ada jeda singkat dengan source dan runtime versi berbeda.

Script membuat symlink untuk `vendor` dan aset build di `public` yang mengarah ke release privat milik akun yang sama. Jika server menolak melayani aset lewat symlink, `/build/*` dapat memberi 403 walaupun deploy selesai; minta hosting mengizinkan symlink milik akun untuk subdomain ini atau gunakan jalur paket manual dalam [panduan cPanel utama](deploy-cpanel.md). Tidak ada source repository atau rahasia yang disalin ke Document Root selain aset publik yang dihasilkan.
