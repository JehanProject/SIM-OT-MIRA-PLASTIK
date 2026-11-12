<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title><?= $product === null ? 'Tambah' : 'Edit' ?> Barang - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/products-form.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main class="product-form-page">
    <div class="product-form-heading">
        <a class="product-form-back" href="<?= site_url('barang') ?>">‹ Kembali ke Data Barang</a>
        <h1><?= $product === null ? 'Tambah barang' : 'Edit barang' ?></h1>
        <p><?= $product === null ? 'Lengkapi informasi barang untuk menambahkannya ke katalog toko.' : 'Perbarui informasi barang tanpa mengubah catatan stok yang sudah ada.' ?></p>
    </div>
    <section class="product-form-card">
    <?php if ($categories === [] || $retailUnits === [] || $wholesaleUnits === []): ?>
        <p class="product-form-notice" role="status">Sebelum membuat barang, tambahkan kategori aktif, satuan berjenis Eceran, dan satuan berjenis Grosir di halaman Data Barang.</p>
    <?php endif ?>
    <form method="post" action="<?= $product === null ? site_url('barang') : site_url('barang/' . $product['product_id'] . '/update') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="product_code">Kode barang</label>
            <input class="form-control" id="product_code" name="product_code" maxlength="80" required value="<?= esc(old('product_code', $product['product_code'] ?? '')) ?>">
            <?php if (isset($errors['product_code'])): ?><span class="error"><?= esc($errors['product_code']) ?></span><?php endif ?>
        </div>
        <div class="field">
            <label for="product_name">Nama barang</label>
            <input class="form-control" id="product_name" name="product_name" maxlength="200" required value="<?= esc(old('product_name', $product['product_name'] ?? '')) ?>">
            <?php if (isset($errors['product_name'])): ?><span class="error"><?= esc($errors['product_name']) ?></span><?php endif ?>
        </div>
        <div class="field">
            <label for="category_id">Kategori</label>
            <select class="form-select" id="category_id" name="category_id" required>
                <option value="">Pilih kategori</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= esc($category['category_id']) ?>" <?= (string) old('category_id', $product['category_id'] ?? '') === (string) $category['category_id'] ? 'selected' : '' ?>><?= esc($category['category_name']) ?></option>
                <?php endforeach ?>
            </select>
            <?php if (isset($errors['category_id'])): ?><span class="error"><?= esc($errors['category_id']) ?></span><?php endif ?>
        </div>
        <div class="field">
            <label for="unit_id">Satuan stok / grosir</label>
            <select class="form-select" id="unit_id" name="unit_id" required>
                <option value="">Pilih satuan stok / grosir</option>
                <?php foreach ($wholesaleUnits as $unit): ?>
                    <option value="<?= esc($unit['unit_id']) ?>" <?= (string) old('unit_id', $product['unit_id'] ?? '') === (string) $unit['unit_id'] ? 'selected' : '' ?>><?= esc($unit['unit_name']) ?> (<?= esc($unit['unit_symbol']) ?>)</option>
                <?php endforeach ?>
            </select>
            <?php if (isset($errors['unit_id'])): ?><span class="error"><?= esc($errors['unit_id']) ?></span><?php endif ?>
        </div>
        <div class="field">
            <label for="retail_unit_id">Satuan eceran</label>
            <select class="form-select" id="retail_unit_id" name="retail_unit_id" required>
                <option value="">Pilih satuan eceran</option>
                <?php foreach ($retailUnits as $unit): ?>
                    <option value="<?= esc($unit['unit_id']) ?>" <?= (string) old('retail_unit_id', $product['retail_unit_id'] ?? $product['unit_id'] ?? '') === (string) $unit['unit_id'] ? 'selected' : '' ?>><?= esc($unit['unit_name']) ?> (<?= esc($unit['unit_symbol']) ?>)</option>
                <?php endforeach ?>
            </select>
            <?php if (isset($errors['retail_unit_id'])): ?><span class="error"><?= esc($errors['retail_unit_id']) ?></span><?php endif ?>
        </div>
        <div class="field">
            <label for="retail_units_per_stock_unit">Jumlah satuan eceran per 1 satuan stok</label>
            <input class="form-control" id="retail_units_per_stock_unit" name="retail_units_per_stock_unit" type="number" min="1" max="1000000" step="0.001" required value="<?= esc(old('retail_units_per_stock_unit', $product['retail_units_per_stock_unit'] ?? '')) ?>" placeholder="Contoh: 12">
            <?php if (isset($errors['retail_units_per_stock_unit'])): ?><span class="error"><?= esc($errors['retail_units_per_stock_unit']) ?></span><?php endif ?>
            <span class="notice">Contoh: isi 12 jika 1 Dus berisi 12 Pcs.</span>
        </div>
        <?php if ($product === null): ?>
            <div class="field">
                <label for="avg_purchase_cost">Harga beli rata-rata awal per satuan stok / grosir (Rp)</label>
                <input class="form-control" id="avg_purchase_cost" name="avg_purchase_cost" type="number" min="0" step="0.01" required value="<?= esc(old('avg_purchase_cost', '0.00')) ?>">
                <?php if (isset($errors['avg_purchase_cost'])): ?><span class="error"><?= esc($errors['avg_purchase_cost']) ?></span><?php endif ?>
            </div>
        <?php else: ?>
            <div class="field">
                <label>Harga beli rata-rata per satuan stok / grosir (Rp)</label>
                <input class="form-control" type="text" value="<?= esc(number_format((float) $product['avg_purchase_cost'], 2, ',', '.')) ?>" readonly>
                <span class="average-cost-note">Diperbarui otomatis saat penerimaan barang.</span>
            </div>
        <?php endif ?>
        <div class="field">
            <label for="retail_price">Harga eceran per satuan eceran (Rp)</label>
            <input class="form-control" id="retail_price" name="retail_price" type="number" min="0" step="0.01" required value="<?= esc(old('retail_price', $product['retail_price'] ?? '0.00')) ?>">
            <?php if (isset($errors['retail_price'])): ?><span class="error"><?= esc($errors['retail_price']) ?></span><?php endif ?>
        </div>
        <div class="field">
            <label for="wholesale_price">Harga grosir per satuan stok / grosir (Rp)</label>
            <input class="form-control" id="wholesale_price" name="wholesale_price" type="number" min="0" step="0.01" required value="<?= esc(old('wholesale_price', $product['wholesale_price'] ?? '0.00')) ?>">
            <?php if (isset($errors['wholesale_price'])): ?><span class="error"><?= esc($errors['wholesale_price']) ?></span><?php endif ?>
        </div>
        <div class="field">
            <label for="wholesale_min_qty">Minimal jumlah grosir (dalam satuan stok / grosir)</label>
            <input class="form-control" id="wholesale_min_qty" name="wholesale_min_qty" type="number" min="0.001" step="0.001" required value="<?= esc(old('wholesale_min_qty', $product['wholesale_min_qty'] ?? '1.000')) ?>">
            <?php if (isset($errors['wholesale_min_qty'])): ?><span class="error"><?= esc($errors['wholesale_min_qty']) ?></span><?php endif ?>
        </div>
        <div class="field">
            <label for="min_stock">Batas minimum stok</label>
            <input class="form-control" id="min_stock" name="min_stock" type="number" min="0" step="0.001" required value="<?= esc(old('min_stock', $product['min_stock'] ?? '0.000')) ?>">
            <?php if (isset($errors['min_stock'])): ?><span class="error"><?= esc($errors['min_stock']) ?></span><?php endif ?>
        </div>
        <p class="form-help">
            Stok dan HPP menggunakan satuan stok; penjualan eceran dikonversi memakai faktor di atas.
            <?php if ($product !== null && $product['is_active']): ?>
                Untuk koreksi stok fisik, <a href="<?= site_url('stok/penyesuaian?product_id=' . $product['product_id']) ?>">gunakan penyesuaian stok</a>; pembelian dicatat melalui penerimaan.
            <?php endif ?>
        </p>
        <div class="actions">
            <button class="btn btn-primary" type="submit">Simpan</button>
            <a class="button btn btn-secondary" href="<?= site_url('barang') ?>">Batal</a>
        </div>
    </form>
    </section>
</main>
</div>
</div>
</body>
</html>
