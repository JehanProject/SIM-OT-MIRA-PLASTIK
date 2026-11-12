<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Penerimaan Barang - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/stock-receipt.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main>
    <section class="card p-4 shadow-sm border-0">
        <h1>Penerimaan barang dari pemasok</h1>
        <p class="notice alert alert-info">Penerimaan menambah stok GUDANG, menghitung ulang rata-rata HPP, dan mencatat pembelian sebagai kas keluar sesuai ruang lingkup sistem. Setiap pemasok perlu memiliki akun pembayaran yang aktif.</p>
        <?php if (isset($success) && $success): ?><p class="notice alert alert-success" role="status"><?= esc($success) ?></p><?php endif ?>
        <?php if (isset($error) && $error): ?><p class="error alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>
    </section>

    <section class="card p-4 shadow-sm border-0">
        <h2>Tambah pemasok</h2>
        <form method="post" action="<?= site_url('stok/pemasok') ?>" class="supplier-form">
            <?= csrf_field() ?>
            <div><label for="supplier_code">Kode pemasok</label><input class="form-control" id="supplier_code" name="supplier_code" maxlength="80" required></div>
            <div><label for="supplier_name">Nama distributor/pemasok</label><input class="form-control" id="supplier_name" name="supplier_name" maxlength="150" required></div>
            <div><label for="phone">Telepon (opsional)</label><input class="form-control" id="phone" name="phone" maxlength="40"></div>
            <div><label for="address">Alamat (opsional)</label><input class="form-control" id="address" name="address" maxlength="500"></div>
            <div><button class="btn btn-primary" type="submit">Simpan pemasok</button></div>
        </form>
    </section>

    <section class="card p-4 shadow-sm border-0">
        <h2>Catat penerimaan</h2>
        <?php foreach ($errors as $message): ?><p class="error alert alert-danger" role="alert"><?= esc($message) ?></p><?php endforeach ?>
        <form method="post" action="<?= site_url('stok/penerimaan') ?>">
            <?= csrf_field() ?>
            <div class="meta">
                <div><label for="supplier_id">Pemasok</label><select class="form-select" id="supplier_id" name="supplier_id" required><option value="">Pilih pemasok</option><?php foreach ($suppliers as $supplier): ?><option value="<?= esc($supplier['supplier_id']) ?>"><?= esc($supplier['supplier_code']) ?> · <?= esc($supplier['supplier_name']) ?></option><?php endforeach ?></select></div>
                <div><label for="cash_account_id">Dibayar dari akun</label><select class="form-select" id="cash_account_id" name="cash_account_id" required><option value="">Pilih akun kas/bank</option><?php foreach ($accounts as $account): ?><option value="<?= esc($account['cash_account_id']) ?>"><?= esc($account['account_name']) ?> (<?= esc($account['account_type']) ?>)</option><?php endforeach ?></select></div>
                <div><label for="received_date">Tanggal diterima</label><input class="form-control" id="received_date" name="received_date" type="date" value="<?= date('Y-m-d') ?>" required></div>
                <div><label for="invoice_number">Nomor faktur (opsional)</label><input class="form-control" id="invoice_number" name="invoice_number" maxlength="100"></div>
            </div>
            <div class="table-wrap">
                <table class="table table-striped align-middle">
                    <thead><tr><th>Barang</th><th>Jumlah diterima</th><th>Harga beli/unit (Rp)</th></tr></thead>
                    <tbody id="receipt-lines">
                    <?php for ($i = 0; $i < 5; $i++): ?>
                        <tr>
                            <td><select class="form-select" name="product_id[]"><option value="">Pilih barang</option><?php foreach ($products as $product): ?><option value="<?= esc($product['product_id']) ?>"><?= esc($product['product_code']) ?> · <?= esc($product['product_name']) ?></option><?php endforeach ?></select></td>
                            <td><input class="form-control" name="quantity[]" type="number" min="0.001" step="0.001" placeholder="0.000"></td>
                            <td><input class="form-control" name="unit_purchase_cost[]" type="number" min="0.01" step="0.01" placeholder="0.00"></td>
                        </tr>
                    <?php endfor ?>
                    </tbody>
                </table>
            </div>
            <div class="actions">
                <button type="button" class="secondary btn btn-secondary" id="add-line">Tambah baris</button>
                <button class="btn btn-primary" type="submit">Posting penerimaan</button>
                <a class="button secondary btn btn-secondary" href="<?= site_url('stok') ?>">Kembali ke stok</a>
            </div>
        </form>
        <?php if ($suppliers === []): ?><p class="muted">Tambahkan pemasok sebelum mencatat penerimaan.</p><?php endif ?>
        <?php if ($products === []): ?><p class="muted">Tambahkan barang aktif sebelum mencatat penerimaan.</p><?php endif ?>
        <?php if ($accounts === []): ?><p class="muted">Akun kas/bank aktif belum tersedia. Periksa data awal akun kas.</p><?php endif ?>
    </section>
</main>
</div>
</div>
<script src="<?= esc(base_url('assets/js/pages/stock-lines.js')) ?>" defer></script>
</body>
</html>
