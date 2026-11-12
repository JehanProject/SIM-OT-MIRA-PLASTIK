<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Laba Rugi Tahunan - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/operations.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main class="ops-page">
    <section class="ops-heading">
        <div><p class="ops-eyebrow">LAPORAN</p><h1>Laporan Laba/Rugi Tahunan</h1><p class="ops-subtitle">Perhitungan memakai penjualan, biaya stok, retur disetujui, dan pengeluaran yang tercatat.</p></div>
        <a class="btn btn-outline-secondary" href="<?= site_url('laporan/laba-rugi?year=' . $year . '&format=csv') ?>">Ekspor CSV</a>
    </section>
    <section class="ops-panel">
        <form class="ops-filter" method="get" action="<?= site_url('laporan/laba-rugi') ?>">
            <label>Tahun<select class="form-select" name="year"><?php for ($optionYear = (int) date('Y'); $optionYear >= (int) date('Y') - 10; $optionYear--): ?><option value="<?= $optionYear ?>" <?= $optionYear === (int) $year ? 'selected' : '' ?>><?= $optionYear ?></option><?php endfor ?></select></label>
            <button class="btn btn-primary" type="submit">Tampilkan</button>
        </form>
    </section>
    <section class="ops-metrics ops-metrics-four">
        <article class="ops-metric"><span>Pendapatan penjualan bersih</span><strong>Rp <?= esc(number_format((float) $revenue, 0, ',', '.')) ?></strong><small>Setelah void dan retur</small></article>
        <article class="ops-metric"><span>Harga pokok penjualan</span><strong>Rp <?= esc(number_format((float) $costOfGoods, 0, ',', '.')) ?></strong><small>Dari ledger pergerakan stok</small></article>
        <article class="ops-metric"><span>Biaya operasional</span><strong>Rp <?= esc(number_format((float) $operatingExpenses, 0, ',', '.')) ?></strong><small>Pengeluaran yang tercatat</small></article>
        <article class="ops-metric ops-metric-emphasis"><span>Laba bersih</span><strong>Rp <?= esc(number_format((float) $netProfit, 0, ',', '.')) ?></strong><small>Margin <?= esc(number_format((float) $margin, 1, ',', '.')) ?>%</small></article>
    </section>
    <section class="ops-panel">
        <div class="ops-panel-heading"><div><h2>Rincian perhitungan</h2><p class="ops-subtitle">Pendapatan − HPP − Biaya Operasional</p></div></div>
        <dl class="ops-calculation">
            <div><dt>Pendapatan penjualan bersih</dt><dd>Rp <?= esc(number_format((float) $revenue, 0, ',', '.')) ?></dd></div>
            <div><dt>− Harga pokok penjualan</dt><dd>Rp <?= esc(number_format((float) $costOfGoods, 0, ',', '.')) ?></dd></div>
            <div><dt>− Biaya operasional</dt><dd>Rp <?= esc(number_format((float) $operatingExpenses, 0, ',', '.')) ?></dd></div>
            <div class="ops-calculation-total"><dt>Laba bersih <?= esc((string) $year) ?></dt><dd>Rp <?= esc(number_format((float) $netProfit, 0, ',', '.')) ?></dd></div>
        </dl>
        <p class="ops-footnote">Penerimaan stok tidak dihitung sebagai biaya operasional. HPP berasal dari biaya pada ledger stok; biaya manual keluar kas ikut dimasukkan.</p>
    </section>
</main>
</div>
</div>
</body>
</html>
