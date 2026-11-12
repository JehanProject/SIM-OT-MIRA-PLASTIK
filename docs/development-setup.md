# Panduan Pengembangan

## MySQL/MariaDB

Gunakan basis data dan akun aplikasi khusus untuk proyek ini. Jangan gunakan
akun root MariaDB untuk aplikasi.

1. Buat basis data lokal dan akun pengguna yang aksesnya dibatasi hanya ke basis
   data tersebut. Jangan simpan kata sandi di sistem kendali versi.
2. Salin `env.example` menjadi `.env`, atur `CI_ENVIRONMENT = development`,
   atur `app.baseURL` ke URL aplikasi lokal (termasuk garis miring di akhir), lalu atur
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

## Deployment staging dan produksi

Gunakan `CI_ENVIRONMENT = production` juga pada staging agar detail error dan
debug toolbar tidak ditampilkan. Konfigurasi harus berbeda per lingkungan;
jangan salin `.env` lokal ke server atau masukkan nilai rahasia ke repositori.

Contoh `.env` staging tanpa kredensial nyata:

```ini
CI_ENVIRONMENT = production
app.baseURL = 'https://staging.example.invalid/'
app.forceGlobalSecureRequests = true
cookie.secure = true
cookie.httponly = true
cookie.samesite = Lax
session.expiration = 1800
database.default.hostname = DB_HOST_FROM_SECRET_MANAGER
database.default.database = mira_plastik
database.default.username = DB_USER_FROM_SECRET_MANAGER
database.default.password = DB_PASSWORD_FROM_SECRET_MANAGER
database.default.DBDriver = MySQLi
database.default.port = 3306
```

Ganti `.invalid` dengan domain staging HTTPS yang benar dan isi nilai database
dari secret manager saat deployment. Akun database aplikasi harus terpisah dari
root dan hanya memiliki hak pada database aplikasi. Konfigurasi menolak mode
production jika URL bukan HTTPS/non-local, HTTPS tidak dipaksa, atau cookie
tidak Secure.

Pada konfigurasi PHP produksi, gunakan `expose_php=Off`,
`display_errors=Off`, dan `log_errors=On`. Simpan error log di lokasi yang
tidak dapat diunduh melalui web dan batasi aksesnya.

Arahkan document root hanya ke direktori `public`. Apache harus mengaktifkan
`mod_rewrite` dan `mod_headers`, mengizinkan `.htaccess` di `public`, serta
memberi akses web hanya ke direktori itu. `.htaccess` di root proyek menolak
seluruh akses jika root proyek keliru dijadikan document root. Untuk Nginx,
gunakan `root .../public`, `try_files $uri $uri/ /index.php?$query_string;`,
tolak dotfiles (kecuali jalur ACME yang memang diperlukan), dan tolak akses ke
`/app`, `/system`, `/writable`, `/vendor`, `/tests`, `/docs`, `.env`, `.git`,
`composer.*`, dan `spark`.

Pasang sertifikat TLS yang valid di web server. Aktifkan HSTS hanya pada virtual
host HTTPS: mulai dengan `max-age=300` di staging, lalu tingkatkan ke
`max-age=31536000` di production setelah seluruh hostname diuji. Jangan
menambahkan `includeSubDomains` atau preload sebelum semua subdomain dipastikan
selalu memakai HTTPS. Jika TLS dihentikan di reverse proxy, proxy harus
menimpa `X-Forwarded-Proto`; percayai hanya alamat proxy yang dikonfigurasi
secara eksplisit di `Config\App::$proxyIPs`.

Redirect HTTP ke HTTPS di web server, bukan hanya di aplikasi: filter aplikasi
tidak menangani file statis yang disajikan langsung oleh web server. Gunakan
hostname kanonis tetap pada redirect, bukan nilai `Host` sembarang dari request.
Contoh konfigurasi Apache:

```apache
<VirtualHost *:80>
    ServerName staging.example.invalid
    Redirect permanent / https://staging.example.invalid/
</VirtualHost>

<VirtualHost *:443>
    ServerName staging.example.invalid
    DocumentRoot /path/to/project/public
    # Konfigurasi TLS dan PHP-FPM/mod_php diletakkan di sini.
<IfModule mod_headers.c>
    Header always set Strict-Transport-Security "max-age=300"
</IfModule>
</VirtualHost>
```

Contoh ekuivalen Nginx (ganti hostname dan path dengan nilai deployment):

```nginx
server {
    listen 80;
    server_name staging.example.invalid;
    return 301 https://staging.example.invalid$request_uri;
}

server {
    listen 443 ssl;
    server_name staging.example.invalid;
    root /path/to/project/public;
    add_header Strict-Transport-Security "max-age=300" always;
    # Konfigurasi TLS dan PHP-FPM diletakkan di sini.
}
```

Pada production, ubah `max-age` menjadi `31536000` setelah staging dan seluruh
hostname deployment lolos verifikasi. CodeIgniter mengirim HSTS pada redirect
HTTP, tetapi browser hanya menerima kebijakan HSTS melalui koneksi HTTPS;
karena itu tetap atur header pada virtual host TLS.

## Checklist verifikasi deployment

- [ ] `CI_ENVIRONMENT` bernilai `production`; `app.baseURL` memakai domain
      staging/production yang benar dan skema HTTPS.
- [ ] HTTP, termasuk URL file statis, dialihkan ke HTTPS; request HTTPS
      mengembalikan HSTS dari web server.
- [ ] Cookie sesi dan CSRF memiliki `Secure`, `HttpOnly`, dan `SameSite=Lax`.
- [ ] Response memuat `X-Frame-Options`, `X-Content-Type-Options`,
      `Referrer-Policy`, serta header HSTS.
- [ ] Form POST tanpa token CSRF ditolak; form dan permintaan API bertoken
      tetap berfungsi.
- [ ] Request ke `/.env`, `/.git/config`, `/composer.json`, `/app/...`,
      `/system/...`, `/writable/...`, dan `/vendor/...` tidak mengembalikan
      konten file (hasil yang diharapkan: 403 atau 404).
- [ ] Document root adalah `public`; directory listing nonaktif; mode
      production tidak menampilkan stack trace atau debug toolbar.
- [ ] `expose_php` nonaktif; error detail disembunyikan dari browser dan dicatat
      hanya ke log yang tidak dapat diakses publik.
- [ ] `.env` dan `.env.*` diabaikan Git (`git check-ignore -v .env
      .env.staging`); template hanya berisi placeholder. Jangan commit dump
      database atau file backup.
- [ ] Database memakai akun aplikasi non-root dengan hak minimum. Buat Owner
      produksi melalui `php spark owner:create`; jangan jalankan seeder akun
      development.

Content Security Policy belum diaktifkan. Uji dahulu di staging dalam mode
report-only dan tinjau skrip/style inline sebelum menerapkan kebijakan yang
memblokir konten.

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
