<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Mutasi Stok - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/stock-transfer.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<?php
$oldProductIds = old('product_id', []);
$oldQuantities = old('quantity', []);
$oldProductIds = is_array($oldProductIds) ? $oldProductIds : [];
$oldQuantities = is_array($oldQuantities) ? $oldQuantities : [];
$oldTransferDate = old('transfer_date', $transferDate);
$oldNotes = old('notes', '');
$lineCount = max(5, count($oldProductIds));
?>
<main>
    <section class="transfer-heading">
        <div><p class="transfer-eyebrow">PERSEDIAAN</p><h1>Mutasi Stok Gudang ke Toko</h1><p class="muted">Pindahkan persediaan untuk kebutuhan penjualan di toko.</p></div>
    </section>
    <?php if ($success): ?><p class="alert alert-success" role="status"><?= esc($success) ?></p><?php endif ?>
    <?php if ($error): ?><p class="alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>
    <?php foreach ($errors as $message): ?><p class="alert alert-danger" role="alert"><?= esc($message) ?></p><?php endforeach ?>
    <section class="transfer-panel">
        <div class="transfer-panel-heading"><div><h2>Informasi mutasi</h2><p>Stok gudang yang tersedia ditampilkan untuk setiap barang.</p></div><span class="transfer-route">Gudang <span aria-hidden="true">→</span> Toko</span></div>
        <form method="post" action="<?= site_url('stok/mutasi') ?>">
            <?= csrf_field() ?>
            <div class="transfer-fields">
                <label>Tanggal<input class="form-control" name="transfer_date" type="date" value="<?= esc(is_string($oldTransferDate) ? $oldTransferDate : $transferDate) ?>" max="<?= esc(date('Y-m-d')) ?>" required></label>
                <label class="transfer-notes">Catatan (opsional)<input class="form-control" name="notes" maxlength="1000" value="<?= esc(is_string($oldNotes) ? $oldNotes : '') ?>" placeholder="Contoh: pemindahan stok untuk penjualan"></label>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Barang</th><th>Stok gudang tersedia</th><th>Jumlah mutasi</th></tr></thead>
                    <tbody id="transfer-lines">
                    <?php for ($index = 0; $index < $lineCount; $index++): ?>
                        <tr>
                            <td><select class="form-select transfer-product" name="product_id[]"><option value="">Pilih barang</option><?php foreach ($products as $product): ?><option value="<?= esc($product['product_id']) ?>" data-stock="<?= esc(format_quantity($product['warehouse_stock'], 6)) ?>" data-unit="<?= esc($product['unit_symbol']) ?>" <?= isset($oldProductIds[$index]) && is_scalar($oldProductIds[$index]) && (string) $oldProductIds[$index] === (string) $product['product_id'] ? 'selected' : '' ?>><?= esc($product['product_code']) ?> · <?= esc($product['product_name']) ?></option><?php endforeach ?></select></td>
                            <td><span class="transfer-stock" aria-live="polite">Pilih barang untuk melihat stok terkini</span></td>
                            <td><input class="form-control" name="quantity[]" type="number" min="0.001" step="0.001" placeholder="0.000" value="<?= esc(is_array($oldQuantities) && isset($oldQuantities[$index]) && is_string($oldQuantities[$index]) ? $oldQuantities[$index] : '') ?>"></td>
                        </tr>
                    <?php endfor ?>
                    </tbody>
                </table>
            </div>
            <div class="transfer-actions"><button class="btn btn-outline-secondary" type="button" id="add-line">Tambah barang</button><button class="btn btn-primary" type="submit" <?= $products === [] ? 'disabled' : '' ?>>Simpan mutasi</button></div>
            <?php if ($products === []): ?><p class="muted">Belum ada barang aktif yang bisa dimutasi.</p><?php endif ?>
        </form>
    </section>
</main>
</div>
</div>
<script src="<?= esc(base_url('assets/js/pages/stock-lines.js')) ?>" defer></script>
</body>
</html>
