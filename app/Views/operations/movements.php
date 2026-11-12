<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Riwayat Pergerakan Stok - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/operations.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main class="ops-page">
    <section class="ops-heading">
        <div>
            <p class="ops-eyebrow">PERSEDIAAN</p>
            <h1>Riwayat Pergerakan Stok</h1>
            <p class="ops-subtitle">Jejak stok masuk, keluar, mutasi, dan penyesuaian dari ledger.</p>
        </div>
        <a class="btn btn-primary" href="<?= site_url('stok/mutasi') ?>">Mutasi stok</a>
    </section>
    <section class="ops-panel">
        <form class="ops-filter" method="get" action="<?= site_url('stok/riwayat') ?>">
            <label>Dari tanggal<input class="form-control" type="date" name="from" value="<?= esc($from) ?>"></label>
            <label>Sampai tanggal<input class="form-control" type="date" name="to" value="<?= esc($to) ?>"></label>
            <label class="ops-filter-search">Cari barang/pengguna<input class="form-control" type="search" name="q" value="<?= esc($search) ?>" placeholder="Kode, nama, atau pengguna"></label>
            <button class="btn btn-primary" type="submit">Terapkan filter</button>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Waktu</th><th>Barang</th><th>Jenis</th><th>Lokasi</th><th>Jumlah</th><th>Dicatat oleh</th><th>Catatan</th></tr></thead>
                <tbody>
                <?php foreach ($movements as $movement): ?>
                    <?php $quantity = (float) $movement['quantity_delta']; ?>
                    <tr>
                        <td><?= esc($movement['occurred_at']) ?></td>
                        <td><strong><?= esc($movement['product_name']) ?></strong><small class="ops-cell-note"><?= esc($movement['product_code']) ?></small></td>
                        <td><?= esc($movement['movement_type']) ?></td>
                        <td><?= esc($movement['location_name']) ?></td>
                        <td class="<?= $quantity < 0 ? 'text-danger' : 'text-success' ?> fw-semibold"><?= $quantity > 0 ? '+' : '' ?><?= esc(format_quantity($quantity, 6)) ?> <?= esc($movement['unit_symbol']) ?></td>
                        <td><?= esc($movement['full_name']) ?></td>
                        <td><?= esc($movement['adjustment_reason']) ?></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($movements === []): ?><tr><td class="ops-empty" colspan="7">Belum ada pergerakan stok pada periode ini.</td></tr><?php endif ?>
                </tbody>
            </table>
        </div>
        <p class="ops-footnote">Menampilkan maksimal 200 pergerakan terbaru.</p>
    </section>
</main>
</div>
</div>
</body>
</html>
