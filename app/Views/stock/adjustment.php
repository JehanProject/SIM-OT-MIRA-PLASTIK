<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Penyesuaian Stok - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/stock-opening.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main>
    <h1>Koreksi stok fisik</h1>
    <p class="notice alert alert-info">Gunakan untuk mencatat selisih hasil hitung fisik, bukan untuk pembelian. Isi perubahan positif untuk menambah atau negatif untuk mengurangi stok. Pengurangan tidak dapat melebihi saldo lokasi. HPP rata-rata tidak berubah; biaya pergerakan memakai HPP rata-rata saat ini.</p>
    <?php if ($error): ?><p class="error alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>
    <?php foreach ($errors as $message): ?><p class="error alert alert-danger" role="alert"><?= esc($message) ?></p><?php endforeach ?>
    <?php if ($products === [] || $locations === []): ?><p class="error alert alert-warning" role="alert">Barang aktif atau lokasi stok belum tersedia.</p><?php endif ?>

    <form method="post" action="<?= site_url('stok/penyesuaian') ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="reason">Alasan koreksi</label>
            <textarea class="form-control" id="reason" name="reason" rows="3" maxlength="1000" required><?= esc(old('reason', '')) ?></textarea>
        </div>
        <div class="table-wrap">
            <table class="table table-striped align-middle">
                <thead><tr><th>Barang</th><th>Lokasi</th><th>Perubahan stok (+/-)</th></tr></thead>
                <tbody id="adjustment-lines">
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <tr>
                        <td>
                            <select class="form-select" name="product_id[]">
                                <option value="">Pilih barang</option>
                                <?php foreach ($products as $product): ?>
                                    <?php $selectedProduct = old('product_id.' . $i, $i === 0 && $selectedProductId !== null ? (string) $selectedProductId : ''); ?>
                                    <option value="<?= esc($product['product_id']) ?>" <?= (string) $selectedProduct === (string) $product['product_id'] ? 'selected' : '' ?>>
                                        <?= esc($product['product_code']) ?> · <?= esc($product['product_name']) ?> (<?= esc($product['unit_symbol']) ?>)
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </td>
                        <td>
                            <select class="form-select" name="location_id[]">
                                <option value="">Pilih lokasi</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?= esc($location['location_id']) ?>" <?= (string) old('location_id.' . $i, '') === (string) $location['location_id'] ? 'selected' : '' ?>>
                                        <?= esc($location['location_name']) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </td>
                        <td><input class="form-control" name="quantity_delta[]" type="number" step="0.001" placeholder="Contoh: 2 atau -1.5" value="<?= esc(old('quantity_delta.' . $i, '')) ?>"></td>
                    </tr>
                <?php endfor ?>
                </tbody>
            </table>
        </div>
        <div class="actions">
            <button type="button" class="secondary btn btn-secondary" id="add-line">Tambah baris</button>
            <button class="btn btn-primary" type="submit" <?= $products === [] || $locations === [] ? 'disabled' : '' ?>>Posting koreksi stok</button>
            <a class="button secondary btn btn-secondary" href="<?= site_url('stok') ?>">Kembali ke stok</a>
        </div>
    </form>
</main>
</div>
</div>
<script src="<?= esc(base_url('assets/js/pages/stock-lines.js')) ?>" defer></script>
</body>
</html>
