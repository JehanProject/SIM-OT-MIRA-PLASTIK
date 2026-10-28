# Panduan Pengembangan

## MySQL/MariaDB

Gunakan basis data dan akun aplikasi khusus untuk proyek ini. Jangan gunakan
akun root MariaDB untuk aplikasi.

1. Buat basis data lokal dan akun pengguna yang aksesnya dibatasi hanya ke basis
   data tersebut. Jangan simpan kata sandi di sistem kendali versi.
2. Salin `env` menjadi `.env`, atur `CI_ENVIRONMENT = development`, atur
   `app.baseURL` ke URL aplikasi lokal (termasuk garis miring di akhir), lalu
   atur
   `database.default.hostname`, `database.default.database`,
   `database.default.username`, `database.default.password`, dan
   `database.default.port`.
3. Jalankan `php spark migrate`.
4. Khusus untuk pengembangan lokal, jalankan
   `php spark db:seed DevelopmentUsersSeeder`. Seeder membuat kata sandi acak
   untuk setiap akun baru dan hanya menampilkannya sekali di CLI. Simpan
   kredensial dengan aman; kata sandi tidak dapat dipulihkan dari hash-nya.
   Jangan pernah menjalankan seeder ini di staging atau produksi.
5. Di lingkungan produksi, buat akun Owner pertama setelah migrasi dengan
   `php spark owner:create`. Perintah ini menolak dijalankan di lingkungan
   selain produksi atau pada basis data yang sudah memiliki Owner. Perintah
   meminta nama lengkap dan username, membaca kata sandi hanya dari variabel
   lingkungan proses `MIRA_OWNER_PASSWORD` (12-72 byte), dan tidak pernah
   menampilkannya. Sediakan variabel tersebut melalui pengelola rahasia untuk
   penerapan aplikasi, lalu hapus dari lingkungan proses. Simpan kredensial di
   pengelola kata sandi yang disetujui. Jangan berikan kata sandi sebagai
   argumen baris perintah.

Untuk staging dan produksi, gunakan `CI_ENVIRONMENT = production`, atur
`app.baseURL` ke URL HTTPS, dan pastikan `app.forceGlobalSecureRequests = true`.
Cookie produksi ditandai Secure.

API autentikasi menyimpan hash kata sandi menggunakan bcrypt di PHP. Lima kali
percobaan kata sandi yang gagal akan mengunci akun selama 15 menit. Sesi
terautentikasi berakhir setelah 30 menit tidak aktif. Permintaan yang mengubah
data menggunakan filter CSRF CodeIgniter.

## Batas migrasi saat ini

Login, verifikasi sesi, dan administrasi pengguna menggunakan tabel relasional
`users`. Produk dan pelanggan grosir disimpan dalam tabel relasional `products`
dan `customers`; migrasi `NormalizeOperationalMasterData` menyalin koleksi
tersebut keluar dari payload state yang ada tanpa menghapus catatannya. API
state tetap menyediakan kontrak berbentuk koleksi yang sama, serta memperbarui
tabel operasional relasional dan baris state berversi dalam satu transaksi.
Migrasi `NormalizeSalesAndLineItems` memindahkan header penjualan, snapshot item,
dan alokasi stok keluar dari payload tanpa mengubah bentuk API yang ada.
Saldo piutang, riwayat pembayaran, dan penghapusan piutang oleh Owner disimpan
di `receivables`, `receivable_payments`, dan `receivable_writeoffs`, yang
diisi awal oleh `NormalizeReceivablesAndWriteoffs`. Riwayat mutasi stok dan
penerimaan pembelian disimpan di `stock_movements` dan `stock_receipts`, yang
diisi awal oleh `NormalizeStockOperations`. Persetujuan retur/void dan snapshot
itemnya disimpan di `return_adjustments` dan `return_items`; catatan kas manual
disimpan di `cash_entries`, yang diisi awal oleh
`NormalizeReturnsAndCashEntries`. Log aktivitas dan data master distributor
disimpan di `activity_logs` dan `distributors`, yang diisi awal oleh
`NormalizeActivityAndDistributors`. Setiap penulisan data harus cocok dengan
revisi terkini. Penulisan bersamaan dengan revisi kedaluwarsa akan ditolak,
bukan diam-diam menimpa data lain. Akses baca/tulis koleksi dibatasi berdasarkan
peran. Saat pertama kali masuk, Owner hanya mengimpor data browser lama jika
state di server masih kosong. Jika server sudah berisi catatan, data browser
lama tetap dipertahankan agar kumpulan data yang ada tidak terhapus diam-diam.

File cadangan tidak lagi mengekspor kata sandi akun. Pemulihan file cadangan
mengganti data operasional di server tanpa mengubah akun server. Fitur
penggabungan cadangan juga bersifat non-destruktif: catatan identik dilewati,
catatan baru ditambahkan, dan konflik ID atau kunci bisnis membatalkan seluruh
pembaruan. Baris JSON state berversi tetap digunakan sebagai jembatan
kompatibilitas dan konkurensi, tetapi semua koleksi operasional disimpan dalam
tabel relasional. Tabel penjualan dan piutang saat ini mempertahankan riwayat,
tetapi belum memiliki kunci asing pada tingkat basis data atau operasi domain
khusus untuk pembayaran. Pengujian migrasi dan pemulihan di MariaDB tetap harus
dilakukan sebelum penerapan aplikasi.
