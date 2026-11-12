<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Stok Toko &amp; Gudang - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/stock-index.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main>
    <section class="stock-heading">
        <div><p class="stock-eyebrow">OPERASIONAL TOKO</p><h1>Stok Toko &amp; Gudang</h1><p>Pantau persediaan di dua lokasi berdasarkan saldo terkini.</p></div>
        <div class="stock-actions">
            <?php if ($canManage): ?>
                <a class="btn btn-primary" href="<?= site_url('stok/mutasi') ?>">Mutasi stok</a>
                <a class="btn btn-outline-secondary" href="<?= site_url('stok/penyesuaian') ?>">Penyesuaian stok</a>
            <?php endif ?>
            <a class="btn btn-outline-secondary" href="<?= site_url('stok/riwayat') ?>">Riwayat pergerakan</a>
        </div>
    </section>
    <?php if ($success): ?><p class="notice alert alert-success" role="status"><?= esc($success) ?></p><?php endif ?>
    <?php if ($error): ?><p class="error alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>

    <section class="stock-summary" aria-label="Ringkasan stok">
        <div><span>Total jenis barang</span><strong><?= esc(count($products)) ?></strong></div>
        <div><span>Perlu restock</span><strong><?= esc($restockCount) ?></strong></div>
        <div><span>Barang habis</span><strong><?= esc($outOfStockCount) ?></strong></div>
    </section>

    <section class="panel card">
        <div class="stock-table-heading"><div><h2>Daftar persediaan</h2><p>Saldo stok toko, gudang, dan batas minimum.</p></div><span class="stock-period">Saldo terkini</span></div>
        <form class="stock-search" method="get" action="<?= site_url('stok') ?>">
            <label class="visually-hidden" for="stock-search">Cari barang</label>
            <input class="form-control" id="stock-search" type="search" name="q" value="<?= esc($search) ?>" placeholder="Cari kode, nama, atau kategori">
            <button class="btn btn-outline-secondary" type="submit">Cari</button>
        </form>
        <div class="table-wrap">
            <table class="table table-hover align-middle">
                <thead><tr><th>Barang</th><th>Stok toko</th><th>Stok gudang</th><th>Batas minimum</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($products as $product): ?>
                    <?php
                        $total = $product['TOKO'] + $product['GUDANG'];
                        $status = $total <= 0 ? 'Habis' : ($total <= $product['min_stock'] ? 'Menipis' : 'Aman');
                    ?>
                    <tr>
                        <td><strong><?= esc($product['product_name']) ?><?= $product['is_active'] ? '' : ' (nonaktif)' ?></strong><small class="stock-product-code"><?= esc($product['product_code']) ?> · <?= esc($product['category_name']) ?></small></td>
                        <td><?= esc(format_quantity($product['TOKO'], 6)) ?> <?= esc($product['unit_symbol']) ?></td>
                        <td><?= esc(format_quantity($product['GUDANG'], 6)) ?> <?= esc($product['unit_symbol']) ?></td>
                        <td><?= esc(format_quantity($product['min_stock'])) ?> <?= esc($product['unit_symbol']) ?></td>
                        <td><span class="stock-badge <?= $total <= 0 ? 'is-out' : ($total <= $product['min_stock'] ? 'is-low' : 'is-safe') ?>"><?= esc($status) ?></span></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($products === []): ?><tr><td colspan="5" class="stock-empty"><?= $search === '' ? 'Belum ada barang. Tambahkan data barang terlebih dahulu.' : 'Tidak ada barang yang cocok dengan pencarian.' ?></td></tr><?php endif ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</div>
</div>
</body>
</html>
