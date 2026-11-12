# Aturan dan Skenario Uji Pengingat Piutang

## Aturan

Tanggal acuan adalah tanggal kalender aplikasi (`Asia/Jakarta`), bukan jam
pembayaran.

- **H-3:** sisa tagihan dengan jatuh tempo dari besok sampai tiga hari ke depan
  (H-3 s.d. H-1) masuk kelompok pengingat yang sama. Tagihan H-4 atau lebih
  jauh belum masuk kelompok pengingat.
- **Jatuh tempo hari ini:** tanggal jatuh tempo sama dengan tanggal acuan.
- **Terlambat:** tanggal jatuh tempo lebih awal dari tanggal acuan.
- Pengingat hanya berlaku bila tanggal jatuh tempo tersedia dan sisa tagihan
  lebih besar dari nol. Sisa dihitung setelah pembayaran dan penyesuaian.
- **Lunas:** status `PAID` tidak muncul dalam daftar tagihan terbuka maupun
  kelompok pengingat.
- **Cicilan sebagian:** status `PARTIALLY_PAID` tetap muncul selama masih ada
  sisa. Pengingat menampilkan sisa tagihan, bukan nilai awal bon.

## Matriks uji

Jalankan pada data uji, bukan transaksi produksi. Tetapkan tanggal acuan dan
buat tagihan dengan nilai contoh berikut:

| Kasus | Status / data | Hasil yang diharapkan |
| --- | --- | --- |
| Tepat H-3 | `OPEN`, jatuh tempo tanggal acuan + 3 hari, sisa Rp1.000.000 | Tampil pada kelompok H-3 s.d. H-1 sebesar Rp1.000.000 |
| H-2 dan H-1 | `OPEN`, jatuh tempo tanggal acuan + 2 atau + 1 hari | Tampil pada kelompok H-3 s.d. H-1 |
| Di luar jendela | `OPEN`, jatuh tempo tanggal acuan + 4 hari | Tidak tampil di pengingat |
| Jatuh tempo hari ini | `OPEN`, jatuh tempo tanggal acuan, sisa Rp1.000.000 | Tampil pada kelompok hari ini |
| Terlambat | `OPEN`, jatuh tempo tanggal acuan - 1 hari, sisa Rp1.000.000 | Tampil pada kelompok terlambat |
| Tagihan lunas | `PAID`, pembayaran sama dengan nilai bon, tanggal jatuh tempo sudah lewat | Tidak tampil di daftar tagihan terbuka atau pengingat |
| Cicilan sebagian, H-3 | `PARTIALLY_PAID`, bon Rp1.000.000, pembayaran Rp400.000, jatuh tempo + 3 hari | Tampil di kelompok H-3 s.d. H-1 dengan sisa Rp600.000 |
| Cicilan sebagian, terlambat | `PARTIALLY_PAID`, masih ada sisa dan jatuh tempo sebelum tanggal acuan | Tampil di kelompok terlambat dengan nilai sisa |
| Tanpa tanggal jatuh tempo | `OPEN`, `due_date` kosong, sisa lebih dari nol | Tampil sebagai tagihan terbuka, tetapi tidak masuk kelompok pengingat |
| Cicilan dilunasi | Setelah kasus cicilan, catat pembayaran terakhir hingga sisa nol dan status `PAID` | Hilang dari daftar tagihan terbuka dan pengingat |

Untuk menguji perubahan status, catat pembayaran melalui formulir Piutang. Pastikan
pembayaran sebagian mengubah status menjadi `PARTIALLY_PAID`; pembayaran yang
melunasi sisa mengubah status menjadi `PAID`. Muat ulang halaman Piutang setelah
setiap transaksi dan periksa kelompok serta nominal sisanya.
