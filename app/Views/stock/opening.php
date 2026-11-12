<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Saldo Awal - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/stock-opening.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main>
    <h1>Catat saldo awal stok</h1>
    <p class="notice alert alert-info">Gunakan untuk memasukkan stok fisik saat mulai memakai sistem. Posting tidak dapat dibatalkan; koreksi berikutnya harus lewat penyesuaian stok. Isi biaya beli rata-rata saat pembukaan agar HPP tercatat benar.</p>
    <?php if (isset($error) && $error): ?><p class="error alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>
    <form method="post" action="<?= site_url('stok/saldo-awal') ?>">
        <?= csrf_field() ?>
        <div class="table-wrap">
            <table class="table table-striped align-middle">
                <thead><tr><th>Barang</th><th>Lokasi</th><th>Jumlah fisik</th><th>Biaya beli/unit (Rp)</th></tr></thead>
                <tbody id="opening-lines">
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <tr>
                        <td><select class="form-select" name="product_id[]"><option value="">Pilih barang</option><?php foreach ($products as $product): ?><option value="<?= esc($product['product_id']) ?>"><?= esc($product['product_code']) ?> · <?= esc($product['product_name']) ?></option><?php endforeach ?></select></td>
                        <td><select class="form-select" name="location_id[]"><option value="">Pilih lokasi</option><?php foreach ($locations as $location): ?><option value="<?= esc($location['location_id']) ?>"><?= esc($location['location_name']) ?></option><?php endforeach ?></select></td>
                        <td><input class="form-control" name="quantity[]" type="number" min="0.001" step="0.001" placeholder="0.000"></td>
                        <td><input class="form-control" name="unit_cost[]" type="number" min="0.01" step="0.01" placeholder="0.00"></td>
                    </tr>
                <?php endfor ?>
                </tbody>
            </table>
        </div>
        <div class="actions">
            <button type="button" class="secondary btn btn-secondary" id="add-line">Tambah baris</button>
            <button class="btn btn-primary" type="submit">Posting saldo awal</button>
            <a class="button secondary btn btn-secondary" href="<?= site_url('stok') ?>">Kembali</a>
        </div>
    </form>
</main>
</div>
</div>
<script src="<?= esc(base_url('assets/js/pages/stock-lines.js')) ?>" defer></script>
</body>
</html>
