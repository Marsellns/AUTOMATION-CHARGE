# Presales

Modul Presales menangani upload PDF dan alur approval berurutan: Uploaded →
Manager NOP → Manager SQ → Manager NOS → Manager NBAE.

Kode antarmuka dan controller menggunakan nama domain Presales:

- `app/Http/Controllers/PresalesController.php`
- `resources/views/presales/`
- route bernama `presales.*` di `routes/web.php`

Model dan tabel tetap bernama `DocumentCirculation` / `document_circulations`
agar data dan migrasi yang sudah berjalan tetap kompatibel. URL lama
`/po-monitoring/document-circulation` tetap tersedia sebagai redirect atau
endpoint kompatibilitas.

## Penolakan dokumen

Penolakan mewajibkan alasan dan konfirmasi kedua pada modal sebelum dikirim.
Setelah tersimpan, pengunggah dokumen dan seluruh manager dengan role approval
Presales menerima notifikasi database yang mengarah ke detail dokumen yang
sama. Manager yang melakukan penolakan tidak menerima notifikasi untuk
tindakannya sendiri.
