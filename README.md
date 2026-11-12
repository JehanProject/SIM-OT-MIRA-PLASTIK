# Sistem Operasional Toko Mira Plastik

Aplikasi internal untuk mengelola barang, stok, penjualan kasir, pembayaran, dan piutang Toko Mira Plastik. Aplikasi dibangun dengan CodeIgniter 4 dan menggunakan MySQL atau MariaDB.

## Fitur

- Login dengan peran `OWNER`, `KASIR`, dan `STAF_GUDANG`.
- Pengelolaan barang, kategori, satuan, harga, serta status aktif.
- Pencatatan saldo awal dan penerimaan barang ke gudang beserta ledger stok.
- Checkout tunai, transfer, dan bon grosir; pembayaran bon dicatat melalui modul piutang.
- Pendaftaran pelanggan grosir, pengisian plafon awal, dan perubahan kebijakan tenor Bon dapat dilakukan Kasir; perubahan plafon setelah pendaftaran tetap dikelola Owner.
- Riwayat transaksi, mutasi stok Toko/Gudang, riwayat pergerakan stok, laporan penjualan, dan laporan laba/rugi tahunan.
- Data distributor, pengelolaan akun, log aktivitas, arsip historis, serta backup dan pemulihan data khusus Owner.
- Profil akun dapat dikelola sendiri dari navbar. Kotak notifikasi menyimpan dan melacak status baca; Owner menerima ringkasan operasional, Kasir menerima info stok Toko/pengajuan, dan Staf Gudang menerima info penerimaan serta stok menipis.
- Pencatatan Arus Kas manual masuk ke ledger akun kas/bank dan ikut dihitung pada laporan yang relevan.
- Metode Bon dapat dipilih hanya untuk penjualan grosir. Checkout Bon tetap menunggu pelanggan aktif dan kebijakan tenor aktif; pelanggan dapat didaftarkan langsung dari kasir.

## Aset Antarmuka

Bootstrap 5.3.8 disimpan lokal di `public/assets/vendor/bootstrap`. CSS bersama berada di `public/assets/css/app.css`, sedangkan CSS per halaman berada di `public/assets/css/pages`. JavaScript bersama dan per halaman berada di `public/assets/js`.

## Persyaratan

- PHP 8.2 atau lebih baru, dengan ekstensi `intl`, `mbstring`, dan `mysqli`.
- Composer.
- MySQL 8.0.16+ atau MariaDB 10.4+.

## Menjalankan Secara Lokal

1. Pasang dependency PHP:

   ```powershell
   composer install
   ```

2. Salin file konfigurasi contoh dan edit `.env`:

   ```powershell
   Copy-Item env .env
   ```

   Atur environment lokal, URL aplikasi, dan koneksi database. Contoh nilai koneksi:

   ```ini
   CI_ENVIRONMENT = development
   app.baseURL = 'http://localhost:8080/'
   database.default.hostname = localhost
   database.default.database = mira_plastik
   database.default.username = USER_DATABASE
   database.default.password = PASSWORD_DATABASE
   database.default.DBDriver = MySQLi
   database.default.port = 3306
   ```

   Ganti username dan password dengan kredensial lokal Anda. Jangan masukkan `.env` atau kredensial ke kontrol versi.

3. Buat skema dan data awal dengan mengimpor [`mira_plastik.sql`](app/Database/mira_plastik.sql) ke MySQL/MariaDB. Skrip ini membuat database `mira_plastik` jika belum ada.

   Untuk database lama, jalankan skrip pembaruan satu kali sesuai kondisi skema: tambahkan satuan eceran dengan [`update_satuan_eceran_grosir.sql`](app/Database/update_satuan_eceran_grosir.sql), jenis satuan dengan [`update_jenis_penjualan_satuan.sql`](app/Database/update_jenis_penjualan_satuan.sql), lalu faktor dan snapshot konversi dengan [`update_konversi_satuan_eceran.sql`](app/Database/update_konversi_satuan_eceran.sql). Jika migrasi konversi sudah pernah dijalankan dengan constraint snapshot lama yang mewajibkan faktor positif, jalankan [`repair_konversi_satuan_eceran.sql`](app/Database/repair_konversi_satuan_eceran.sql) satu kali sebagai gantinya. Untuk mengaktifkan status piutang yang selesai karena koreksi retur, jalankan [`update_sale_adjustment_workflow.sql`](app/Database/update_sale_adjustment_workflow.sql) satu kali. Untuk pencatatan Arus Kas manual, jalankan [`update_manual_cash_transactions.sql`](app/Database/update_manual_cash_transactions.sql) satu kali. Untuk kotak notifikasi pengguna, jalankan [`update_user_notifications.sql`](app/Database/update_user_notifications.sql) satu kali. Jangan jalankan skrip pembaruan pada database baru yang sudah dibuat dari skema terkini.

4. Buat akun Owner pertama melalui terminal. Perintah akan meminta nama dan username, lalu menampilkan kata sandi yang dibuat secara acak. Simpan kata sandi tersebut karena hanya ditampilkan sekali.

   ```powershell
   php spark owner:create
   ```

5. Jalankan server pengembangan:

   ```powershell
   php spark serve --host 127.0.0.1 --port 8080
   ```

   Buka halaman login di <http://localhost:8080/index.php/login>.

## Alur Penggunaan

1. Login dengan akun Owner, lalu buat kategori dan satuan eceran/grosir.
2. Buat barang, tentukan harga dan batas stok, kemudian catat saldo awal atau penerimaan barang.
3. Gunakan halaman kasir untuk penjualan tunai/transfer atau bon grosir.
4. Atur kebijakan tenor dan data pelanggan sebelum mengaktifkan transaksi bon.
5. Catat cicilan atau pelunasan melalui halaman piutang.
6. Dari struk, ajukan Void/Retur; Owner/Kasir memutuskan melalui Persetujuan Void/Retur. Refund tunai/transfer dicatat satu kali penuh setelah uang benar-benar dikembalikan.

Rute utama: `/login`, `/dashboard`, `/profil`, `/notifikasi`, `/barang`, `/stok`, `/stok/mutasi`, `/stok/riwayat`, `/kasir`, `/transaksi`, `/piutang`, `/persetujuan`, `/laporan/penjualan`, `/laporan/laba-rugi`, `/arus-kas`, `/distributor`, `/pengguna`, `/aktivitas`, `/arsip`, dan `/backup`.

## Batasan Saat Ini

Checkout sudah mengonversi satuan eceran ke satuan stok dan menyimpan snapshot jumlah serta satuan transaksi. Perilaku konversi, harga, alokasi lokasi, struk, dan rollback sudah diuji end-to-end dengan fixture QA; constraint aktif sudah diselaraskan. Jangan mengaktifkan penjualan eceran untuk barang yang faktor konversinya belum diketahui dan terverifikasi.

Alur checkout bon, pembayaran cicilan/pelunasan, serta pengujian plafon dan tenor belum divalidasi menyeluruh dengan data uji.

Alur pengajuan dan persetujuan Void/Retur telah ditambahkan. Sebelum digunakan pada transaksi nyata, validasi alur ini dengan data uji yang disetujui; terutama Void tunai/transfer, retur parsial, Bon yang sudah dibayar sebagian/lunas, penolakan, dan pencatatan refund satu kali.

## Deployment dan Keamanan

- Arahkan document root web server ke folder `public`, bukan ke root proyek.
- Gunakan `CI_ENVIRONMENT = production` dan HTTPS untuk deployment.
- Batasi akses ke `.env` dan folder `writable`; jangan menyimpan kredensial database di file yang dapat diunduh publik.
- Cadangkan database secara berkala sebelum menjalankan perubahan skema atau operasi data.
- Backup JSON dapat dibuat manual oleh Owner dari halaman **Backup & Restore** atau melalui `php spark backup:create`. File disimpan di `writable/backups`, hanya dapat diakses Owner, dan perintah CLI membersihkan backup yang berumur lebih dari 30 hari. Untuk backup harian otomatis, jadwalkan perintah CLI tersebut melalui Task Scheduler Windows atau cron; aplikasi tidak mengaktifkan scheduler sistem secara otomatis. Pemulihan mensyaratkan konfirmasi teks dan membuat backup pengaman terlebih dahulu.
