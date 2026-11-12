<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Riwayat Transaksi - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/operations.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main class="ops-page">
    <section class="ops-heading">
        <div>
            <p class="ops-eyebrow">PENJUALAN</p>
            <h1>Riwayat Transaksi</h1>
            <p class="ops-subtitle">Transaksi tersimpan setelah pembayaran berhasil.</p>
        </div>
        <a class="btn btn-primary" href="<?= site_url('kasir') ?>">Transaksi baru</a>
    </section>

    <section class="ops-panel">
        <form class="ops-filter" method="get" action="<?= site_url('transaksi') ?>">
            <label>Dari tanggal<input class="form-control" type="date" name="from" value="<?= esc($from) ?>"></label>
            <label>Sampai tanggal<input class="form-control" type="date" name="to" value="<?= esc($to) ?>"></label>
            <label class="ops-filter-search">Cari faktur/pelanggan/kasir<input class="form-control" type="search" name="q" value="<?= esc($search) ?>" placeholder="Masukkan kata kunci"></label>
            <button class="btn btn-primary" type="submit">Terapkan filter</button>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Nomor</th><th>Tanggal</th><th>Pelanggan</th><th>Kasir</th><th>Jenis</th><th>Metode</th><th>Total</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><strong><?= esc($transaction['invoice_number']) ?></strong></td>
                        <td><?= esc($transaction['sold_at']) ?></td>
                        <td><?= esc($transaction['customer_name'] ?? 'Pelanggan umum') ?></td>
                        <td><?= esc($transaction['full_name']) ?></td>
                        <td><?= esc(ucfirst(strtolower($transaction['sale_type']))) ?></td>
                        <td><?= esc(ucfirst(strtolower($transaction['payment_method']))) ?></td>
                        <td>Rp <?= esc(number_format((float) $transaction['total_amount'], 0, ',', '.')) ?></td>
                        <td><span class="ops-status <?= $transaction['status'] === 'POSTED' ? 'is-positive' : 'is-neutral' ?>"><?= esc($transaction['status']) ?></span></td>
                        <td><a href="<?= site_url('kasir/struk/' . $transaction['sale_id']) ?>">Lihat struk</a></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($transactions === []): ?><tr><td class="ops-empty" colspan="9">Belum ada transaksi pada periode ini.</td></tr><?php endif ?>
                </tbody>
            </table>
        </div>
        <p class="ops-footnote">Menampilkan maksimal 100 transaksi terbaru.</p>
    </section>
</main>
</div>
</div>
</body>
</html>
