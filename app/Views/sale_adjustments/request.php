<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Ajukan Void/Retur - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/sale-adjustments.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main>
    <div class="toolbar">
        <div>
            <h1>Ajukan Void/Retur</h1>
            <p class="muted">Faktur <?= esc($sale['invoice_number']) ?> · <?= esc($sale['sale_type']) ?> · <?= esc($sale['payment_method']) ?></p>
        </div>
        <a class="button btn btn-secondary" href="<?= site_url('kasir/struk/' . $sale['sale_id']) ?>">Kembali ke struk</a>
    </div>

    <?php if ($success): ?><p class="alert alert-success" role="status"><?= esc($success) ?></p><?php endif ?>
    <?php if ($error): ?><p class="alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>

    <?php if (! $canRequest): ?>
        <section class="panel card">
            <h2>Pengajuan tidak tersedia</h2>
            <p class="muted">Transaksi sudah selesai/void, tidak memiliki alokasi stok, atau masih memiliki pengajuan yang menunggu keputusan.</p>
        </section>
    <?php else: ?>
        <section class="panel card">
            <h2>Aturan pengajuan</h2>
            <ul>
                <li>Owner atau Kasir dapat menyetujui pengajuan, termasuk pengajuan yang dibuat sendiri.</li>
                <li>Barang yang diajukan untuk retur harus layak dijual kembali. Lokasi pemulihan stok dipilih di bawah.</li>
                <li>Refund belum mengurangi kas sampai uang benar-benar dikembalikan dan dicatat di menu persetujuan.</li>
                <?php if ($sale['payment_method'] === 'BON'): ?>
                    <li>Retur mengurangi sisa piutang terlebih dahulu. Kelebihan nilai retur menjadi refund tertunda.</li>
                <?php endif ?>
            </ul>
        </section>
        <form class="panel card adjustment-form" method="post" action="<?= site_url('kasir/struk/' . $sale['sale_id'] . '/penyesuaian') ?>">
            <?= csrf_field() ?>
            <label class="form-label" for="adjustment_type">Jenis pengajuan</label>
            <select class="form-select" id="adjustment_type" name="adjustment_type" required>
                <option value="RETUR" <?= old('adjustment_type') === 'VOID' ? '' : 'selected' ?>>Retur sebagian atau seluruh barang</option>
                <?php if ($sale['status'] === 'POSTED'): ?>
                    <option value="VOID" <?= old('adjustment_type') === 'VOID' ? 'selected' : '' ?>>Void transaksi penuh</option>
                <?php endif ?>
            </select>
            <label class="form-label" for="reason">Alasan</label>
            <textarea class="form-control" id="reason" name="reason" rows="3" maxlength="1000" required><?= esc(old('reason', '')) ?></textarea>

            <h2>Barang yang diajukan</h2>
            <p class="muted">Untuk Void, seluruh alokasi stok yang tersisa akan dipulihkan ke lokasi asal. Untuk Retur, isi jumlah pada setiap alokasi yang dikembalikan.</p>
            <div class="table-wrap">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Barang dan lokasi asal</th><th>Stok tersisa untuk diretur</th><th>Jumlah retur</th><th>Lokasi stok kembali</th></tr></thead>
                    <tbody>
                    <?php foreach ($allocations as $allocation): ?>
                        <?php if ((float) $allocation['remaining_quantity'] <= 0): continue; endif ?>
                        <tr>
                            <td><?= esc($allocation['product_code_snapshot']) ?> · <?= esc($allocation['product_name_snapshot']) ?><br><span class="muted"><?= esc($allocation['location_name']) ?></span></td>
                            <td><?= esc(format_quantity($allocation['remaining_quantity'], 6)) ?> <?= esc($allocation['unit_snapshot']) ?></td>
                            <td><input class="form-control" name="quantities[<?= esc($allocation['allocation_id']) ?>]" type="number" min="0.000001" max="<?= esc($allocation['remaining_quantity']) ?>" step="0.000001" value="<?= esc(old('quantities.' . $allocation['allocation_id'], '')) ?>" aria-label="Jumlah retur <?= esc($allocation['product_name_snapshot']) ?>"></td>
                            <td>
                                <select class="form-select" name="restock_location_ids[<?= esc($allocation['allocation_id']) ?>]" aria-label="Lokasi stok kembali <?= esc($allocation['product_name_snapshot']) ?>">
                                    <option value="">Pilih lokasi</option>
                                    <?php foreach ($locations as $location): ?>
                                        <option value="<?= esc($location['location_id']) ?>" <?= old('restock_location_ids.' . $allocation['allocation_id'], (string) $allocation['location_id']) === (string) $location['location_id'] ? 'selected' : '' ?>>
                                            <?= esc($location['location_name']) ?>
                                        </option>
                                    <?php endforeach ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <button class="btn btn-primary" type="submit">Kirim pengajuan</button>
        </form>
    <?php endif ?>
</main>
</body>
</html>
