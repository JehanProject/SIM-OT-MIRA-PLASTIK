<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Laporan Penjualan - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/operations.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main class="ops-page">
    <section class="ops-heading">
        <div>
            <p class="ops-eyebrow">LAPORAN</p>
            <h1>Laporan Penjualan</h1>
            <p class="ops-subtitle">Pantau tren penjualan eceran dan grosir berdasarkan transaksi tersimpan.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?= current_url() . '?from=' . rawurlencode($from) . '&to=' . rawurlencode($to) . '&format=csv' ?>">Ekspor CSV</a>
    </section>
    <section class="ops-panel">
        <form class="ops-filter" method="get" action="<?= site_url('laporan/penjualan') ?>">
            <label>Dari tanggal<input class="form-control" type="date" name="from" value="<?= esc($from) ?>"></label>
            <label>Sampai tanggal<input class="form-control" type="date" name="to" value="<?= esc($to) ?>"></label>
            <button class="btn btn-primary" type="submit">Terapkan periode</button>
        </form>
    </section>
    <section class="ops-metrics">
        <article class="ops-metric"><span>Total penjualan bersih</span><strong>Rp <?= esc(number_format((float) $summary['net_sales'], 0, ',', '.')) ?></strong><small>Void dan nilai retur disesuaikan</small></article>
        <article class="ops-metric"><span>Transaksi</span><strong><?= esc(number_format((int) $summary['transaction_count'], 0, ',', '.')) ?></strong><small>Rata-rata Rp <?= esc(number_format((float) $average, 0, ',', '.')) ?></small></article>
        <article class="ops-metric"><span>Kontribusi grosir</span><strong><?= esc(number_format((float) $wholesalePercent, 1, ',', '.')) ?>%</strong><small>Rp <?= esc(number_format((float) $summary['wholesale_sales'], 0, ',', '.')) ?></small></article>
    </section>
    <section class="ops-panel">
        <div class="ops-panel-heading"><div><h2>Penjualan per hari</h2><p class="ops-subtitle">Nilai bersih pada periode yang dipilih.</p></div></div>
        <?php $maxSales = max(array_map(static fn (array $row): float => (float) $row['net_sales'], $daily) ?: [0]); ?>
        <div class="ops-chart" aria-label="Grafik penjualan per hari">
            <?php foreach ($daily as $day): ?>
                <?php $barHeight = $maxSales > 0 ? max(4, (float) $day['net_sales'] / $maxSales * 100) : 0; ?>
                <div class="ops-chart-column"><strong>Rp <?= esc(number_format((float) $day['net_sales'], 0, ',', '.')) ?></strong><span class="ops-chart-track"><span class="ops-chart-bar" style="height: <?= esc((string) $barHeight) ?>%"></span></span><small><?= esc(date('d/m', strtotime($day['sale_date']))) ?></small></div>
            <?php endforeach ?>
            <?php if ($daily === []): ?><p class="ops-empty">Belum ada penjualan untuk digambarkan.</p><?php endif ?>
        </div>
    </section>
    <section class="ops-panel">
        <div class="ops-panel-heading"><div><h2>Rincian penjualan</h2><p class="ops-subtitle"><?= esc($from) ?> sampai <?= esc($to) ?></p></div></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Nomor</th><th>Tanggal</th><th>Pelanggan</th><th>Jenis</th><th>Metode</th><th>Total bersih</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?><tr><td><?= esc($row['invoice_number']) ?></td><td><?= esc($row['sold_at']) ?></td><td><?= esc($row['customer_name']) ?></td><td><?= esc($row['sale_type']) ?></td><td><?= esc($row['payment_method']) ?></td><td>Rp <?= esc(number_format((float) $row['net_amount'], 0, ',', '.')) ?></td><td><?= esc($row['status']) ?></td></tr><?php endforeach ?>
            <?php if ($rows === []): ?><tr><td colspan="7" class="ops-empty">Belum ada transaksi tersimpan.</td></tr><?php endif ?>
            </tbody>
        </table></div>
        <p class="ops-footnote">Menampilkan maksimal 500 transaksi.</p>
    </section>
</main>
</div>
</div>
</body>
</html>
