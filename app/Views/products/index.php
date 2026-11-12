<?php
/**
 * @var array<int, array<string, mixed>> $products
 * @var array<int|string, array<string, mixed>> $stockByProduct
 * @var array<int, array<string, mixed>> $categories
 * @var array<int, array<string, mixed>> $units
 * @var string $search
 * @var bool $showInactive
 * @var string $roleCode
 * @var string|null $success
 * @var string|null $error
 * @var array<int|string, string> $formErrors
 */
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Barang dan Stok - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/products-index.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main class="products-page">
    <section class="products-heading">
        <div>
            <p class="products-eyebrow">Persediaan</p>
            <h1>Data barang</h1>
            <p class="products-subtitle">Kelola informasi produk, harga, dan ketersediaan stok toko serta gudang.</p>
        </div>
        <div class="products-heading-actions">
            <a class="products-button products-button-secondary" href="<?= site_url('stok') ?>">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16v13H4zM8 7V4h8v3m-8 5h8m-8 4h5"></path></svg>
                Kelola stok
            </a>
            <?php if (in_array($roleCode, ['OWNER', 'STAF_GUDANG'], true)): ?>
                <a class="products-button products-button-primary" href="<?= site_url('barang/baru') ?>">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14m-7-7h14"></path></svg>
                    Tambah barang
                </a>
            <?php endif ?>
        </div>
    </section>

    <?php if ($success): ?><p class="products-notice products-notice-success" role="status"><?= esc($success) ?></p><?php endif ?>
    <?php if ($error): ?><p class="products-notice products-notice-error" role="alert"><?= esc($error) ?></p><?php endif ?>
    <?php foreach ($formErrors as $message): ?><p class="products-notice products-notice-error" role="alert"><?= esc($message) ?></p><?php endforeach ?>

    <?php if (in_array($roleCode, ['OWNER', 'STAF_GUDANG'], true)): ?>
        <details class="products-settings">
            <summary>
                <span>
                    <strong>Pengaturan kategori dan satuan</strong>
                    <small><?= count($categories) ?> kategori · <?= count($units) ?> satuan tersedia</small>
                </span>
                <span class="products-settings-chevron" aria-hidden="true">⌄</span>
            </summary>
            <div class="products-settings-grid">
                <section class="products-settings-card">
                    <h2>Kategori</h2>
                    <p class="products-helper"><?= count($categories) ?> kategori tercatat. Kategori yang sudah dipakai dinonaktifkan, bukan dihapus.</p>
                    <form class="products-inline-form" method="post" action="<?= site_url('barang/kategori') ?>">
                        <?= csrf_field() ?>
                        <label class="visually-hidden" for="category_name">Nama kategori baru</label>
                        <input class="form-control" id="category_name" name="category_name" maxlength="100" placeholder="Nama kategori baru" required>
                        <button class="products-button products-button-primary" type="submit">Tambah</button>
                    </form>
                    <?php if ($categories !== []): ?>
                        <ul class="products-settings-list">
                            <?php foreach ($categories as $category): ?>
                                <li>
                                    <span><?= esc($category['category_name']) ?></span>
                                    <?php if ($category['is_active']): ?>
                                        <form method="post" action="<?= site_url('barang/kategori/' . $category['category_id'] . '/nonaktifkan') ?>">
                                            <?= csrf_field() ?>
                                            <button class="products-text-button" type="submit">Nonaktifkan</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="products-badge products-badge-inactive">Nonaktif</span>
                                    <?php endif ?>
                                </li>
                            <?php endforeach ?>
                        </ul>
                    <?php endif ?>
                </section>

                <section class="products-settings-card">
                    <h2>Satuan</h2>
                    <p class="products-helper"><?= count($units) ?> satuan. Jenis satuan menentukan penggunaan untuk penjualan eceran atau grosir.</p>
                    <form class="products-unit-form" method="post" action="<?= site_url('barang/satuan') ?>">
                        <?= csrf_field() ?>
                        <div>
                            <label class="visually-hidden" for="unit_name">Nama satuan</label>
                            <input class="form-control" id="unit_name" name="unit_name" maxlength="100" placeholder="Nama (contoh: Pak)" required>
                        </div>
                        <div>
                            <label class="visually-hidden" for="unit_symbol">Simbol satuan</label>
                            <input class="form-control" id="unit_symbol" name="unit_symbol" maxlength="30" placeholder="Simbol (contoh: pak)" required>
                        </div>
                        <div>
                            <label class="visually-hidden" for="sale_type">Jenis penjualan</label>
                            <select class="form-select" id="sale_type" name="sale_type" required>
                                <option value="">Jenis penjualan</option>
                                <option value="ECERAN" <?= old('sale_type') === 'ECERAN' ? 'selected' : '' ?>>Eceran</option>
                                <option value="GROSIR" <?= old('sale_type') === 'GROSIR' ? 'selected' : '' ?>>Grosir</option>
                            </select>
                        </div>
                        <button class="products-button products-button-primary" type="submit">Tambah satuan</button>
                    </form>
                    <ul class="products-settings-list products-unit-list">
                        <?php foreach ($units as $unit): ?>
                            <li>
                                <span><?= esc($unit['unit_name']) ?> <small>(<?= esc($unit['unit_symbol']) ?>)</small></span>
                                <span class="products-badge"><?= esc(ucfirst(strtolower($unit['sale_type']))) ?></span>
                            </li>
                        <?php endforeach ?>
                    </ul>
                </section>
            </div>
        </details>
    <?php endif ?>

    <section class="products-catalog">
        <div class="products-catalog-heading">
            <div>
                <h2>Daftar barang</h2>
                <p><?= count($products) ?> barang<?= $showInactive ? ' · termasuk barang nonaktif' : ' aktif' ?></p>
            </div>
        </div>

        <form class="products-filters" method="get" action="<?= site_url('barang') ?>">
            <div class="products-search">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 5 5"></path></svg>
                <label class="visually-hidden" for="product-search">Cari barang</label>
                <input id="product-search" type="search" name="q" value="<?= esc($search) ?>" placeholder="Cari kode, nama, atau kategori">
            </div>
            <label class="products-inactive-filter">
                <input class="form-check-input" type="checkbox" name="status" value="all" <?= $showInactive ? 'checked' : '' ?>>
                Tampilkan barang nonaktif
            </label>
            <button class="products-button products-button-secondary" type="submit">Cari</button>
        </form>

        <div class="products-table-wrap">
            <table class="products-table">
                <thead>
                <tr>
                    <th scope="col">Barang</th>
                    <th scope="col">Kategori</th>
                    <th scope="col">Satuan dan konversi</th>
                    <th scope="col">Harga eceran</th>
                    <th scope="col">Harga grosir</th>
                    <th scope="col">Minimal grosir</th>
                    <th scope="col">Stok toko</th>
                    <th scope="col">Stok gudang</th>
                    <th scope="col">Stok minimum</th>
                    <th scope="col">Status stok</th>
                    <th scope="col">Status</th>
                    <?php if (in_array($roleCode, ['OWNER', 'STAF_GUDANG'], true)): ?><th scope="col">Aksi</th><?php endif ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $product): ?>
                    <?php
                        $storeStock = (float) ($stockByProduct[$product['product_id']]['TOKO'] ?? 0);
                        $warehouseStock = (float) ($stockByProduct[$product['product_id']]['GUDANG'] ?? 0);
                        $totalStock = $storeStock + $warehouseStock;
                        $minimumStock = (float) $product['min_stock'];
                        $stockStatus = $totalStock <= 0 ? 'Habis' : ($minimumStock > 0 && $totalStock <= $minimumStock ? 'Menipis' : 'Aman');
                        $stockStatusClass = $totalStock <= 0 ? 'products-badge-out' : ($stockStatus === 'Menipis' ? 'products-badge-low' : 'products-badge-ok');
                    ?>
                    <tr>
                        <td>
                            <strong class="products-name"><?= esc($product['product_name']) ?></strong>
                            <span class="products-code"><?= esc($product['product_code']) ?></span>
                        </td>
                        <td><?= esc($product['category_name']) ?></td>
                        <td>
                            <span><?= esc($product['retail_unit_symbol']) ?></span>
                            <span class="products-cell-detail"><?= $product['retail_units_per_stock_unit'] === null ? 'Konversi belum diatur' : '1 ' . esc($product['unit_symbol']) . ' = ' . esc(format_quantity($product['retail_units_per_stock_unit'])) . ' ' . esc($product['retail_unit_symbol']) ?></span>
                        </td>
                        <td>Rp <?= esc(number_format((float) $product['retail_price'], 2, ',', '.')) ?><span class="products-cell-detail">/ <?= esc($product['retail_unit_symbol']) ?></span></td>
                        <td>Rp <?= esc(number_format((float) $product['wholesale_price'], 2, ',', '.')) ?><span class="products-cell-detail">/ <?= esc($product['unit_symbol']) ?></span></td>
                        <td><?= esc(format_quantity($product['wholesale_min_qty'])) ?> <?= esc($product['unit_symbol']) ?></td>
                        <td><?= esc(format_quantity($storeStock, 6)) ?> <?= esc($product['unit_symbol']) ?></td>
                        <td><?= esc(format_quantity($warehouseStock, 6)) ?> <?= esc($product['unit_symbol']) ?></td>
                        <td><?= esc(format_quantity($minimumStock, 6)) ?> <?= esc($product['unit_symbol']) ?></td>
                        <td><span class="products-badge <?= esc($stockStatusClass) ?>"><?= esc($stockStatus) ?></span></td>
                        <td><span class="products-badge <?= $product['is_active'] ? 'products-badge-active' : 'products-badge-inactive' ?>"><?= $product['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                        <?php if (in_array($roleCode, ['OWNER', 'STAF_GUDANG'], true)): ?>
                            <td>
                                <div class="products-row-actions">
                                    <a class="products-row-edit" href="<?= site_url('barang/' . $product['product_id'] . '/edit') ?>">Edit</a>
                                    <?php if ($product['is_active']): ?>
                                        <form method="post" action="<?= site_url('barang/' . $product['product_id'] . '/nonaktifkan') ?>">
                                            <?= csrf_field() ?>
                                            <button class="products-row-danger" type="submit">Nonaktifkan</button>
                                        </form>
                                    <?php endif ?>
                                    <?php if (! isset($productsWithHistory[(int) $product['product_id']])): ?>
                                        <form method="post" action="<?= site_url('barang/' . $product['product_id'] . '/hapus') ?>" data-confirm="Hapus permanen barang ini? Tindakan ini tidak dapat dibatalkan.">
                                            <?= csrf_field() ?>
                                            <button class="products-row-danger" type="submit">Hapus</button>
                                        </form>
                                    <?php endif ?>
                                </div>
                            </td>
                        <?php endif ?>
                    </tr>
                <?php endforeach ?>
                <?php if ($products === []): ?>
                    <tr>
                        <td class="products-empty" colspan="<?= in_array($roleCode, ['OWNER', 'STAF_GUDANG'], true) ? '12' : '11' ?>">
                            <strong>Barang tidak ditemukan</strong>
                            <span>Coba ubah kata kunci pencarian atau tambahkan barang baru.</span>
                        </td>
                    </tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </section>

    <p class="products-footnote">Barang baru dimulai dengan stok nol. Stok hanya berubah melalui penerimaan, saldo awal, mutasi, atau penyesuaian stok.</p>
</main>
</div>
</div>
</body>
</html>
