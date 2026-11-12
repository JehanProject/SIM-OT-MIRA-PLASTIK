<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Data pelanggan grosir - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/receivables-customers.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main>
    <div class="toolbar"><h1>Data pelanggan grosir</h1><a class="button btn btn-secondary" href="<?= $roleCode === 'KASIR' ? site_url('kasir') : site_url('piutang') ?>"><?= $roleCode === 'KASIR' ? 'Kembali ke kasir' : 'Kembali ke piutang' ?></a></div>
    <?php if ($success): ?><p class="notice alert alert-success" role="status"><?= esc($success) ?></p><?php endif ?>
    <?php if ($error): ?><p class="error alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>
    <?php foreach ($formErrors as $message): ?><p class="error alert alert-danger" role="alert"><?= esc($message) ?></p><?php endforeach ?>

    <section class="panel card">
        <h2>Tambah pelanggan</h2>
        <p class="muted">Plafon bon adalah batas seluruh piutang terbuka pelanggan, bukan nilai per nota. <?= $roleCode === 'KASIR' ? 'Masukkan plafon sesuai persetujuan pelanggan/toko; pengaturan tenor toko tersedia pada alur Bon grosir.' : '' ?></p>
        <form method="post" action="<?= site_url('piutang/pelanggan') ?>">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div><label for="customer-code">Kode pelanggan</label><input class="form-control" id="customer-code" name="customer_code" maxlength="80" value="<?= esc((string) old('customer_code', '')) ?>" required></div>
                <div><label for="customer-name">Nama pelanggan</label><input class="form-control" id="customer-name" name="customer_name" maxlength="150" value="<?= esc((string) old('customer_name', '')) ?>" required></div>
                <div><label for="business-name">Nama usaha</label><input class="form-control" id="business-name" name="business_name" maxlength="150" value="<?= esc((string) old('business_name', '')) ?>"></div>
                <div><label for="phone">Nomor telepon</label><input class="form-control" id="phone" name="phone" maxlength="40" value="<?= esc((string) old('phone', '')) ?>"></div>
                <div><label for="credit-limit">Plafon bon (Rp)</label><input class="form-control" id="credit-limit" name="credit_limit" type="number" min="0" step="0.01" value="<?= esc((string) old('credit_limit', '0')) ?>" required></div>
            </div>
            <label for="address">Alamat</label><textarea class="form-control" id="address" name="address" rows="2"><?= esc((string) old('address', '')) ?></textarea>
            <p><button class="btn btn-primary" type="submit">Simpan pelanggan</button></p>
        </form>
    </section>

    <section class="panel card">
        <h2>Daftar pelanggan</h2>
        <div class="table-wrap">
            <table class="table table-hover align-middle">
                <thead><tr><th>Kode</th><th>Nama pelanggan</th><th>Usaha</th><th>Telepon</th><th>Plafon bon</th><th>Piutang terbuka</th><th>Status</th><?php if ($roleCode === 'OWNER'): ?><th>Aksi</th><?php endif ?></tr></thead>
                <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><?= esc($customer['customer_code']) ?></td>
                        <td><?= esc($customer['customer_name']) ?></td>
                        <td><?= esc($customer['business_name'] ?? '-') ?></td>
                        <td><?= esc($customer['phone'] ?? '-') ?></td>
                        <td>
                            <?php if ($roleCode === 'OWNER'): ?>
                                <form class="toolbar" method="post" action="<?= site_url('piutang/pelanggan/' . $customer['customer_id'] . '/plafon') ?>">
                                    <?= csrf_field() ?>
                                    <input class="form-control" name="credit_limit" type="number" min="<?= esc($customer['outstanding_amount']) ?>" step="0.01" value="<?= esc($customer['credit_limit']) ?>" required aria-label="Plafon bon pelanggan">
                                    <button class="btn btn-primary" type="submit">Ubah</button>
                                </form>
                            <?php else: ?>
                                Rp <?= esc(number_format((float) $customer['credit_limit'], 2, ',', '.')) ?>
                            <?php endif ?>
                        </td>
                        <td>Rp <?= esc(number_format((float) $customer['outstanding_amount'], 2, ',', '.')) ?></td>
                        <td><?= $customer['is_active'] ? 'Aktif' : 'Nonaktif' ?></td>
                        <?php if ($roleCode === 'OWNER'): ?>
                            <td>
                                <?php if ($customer['is_active']): ?>
                                    <form method="post" action="<?= site_url('piutang/pelanggan/' . $customer['customer_id'] . '/nonaktifkan') ?>">
                                        <?= csrf_field() ?>
                                        <button class="danger btn btn-danger" type="submit">Nonaktifkan</button>
                                    </form>
                                <?php endif ?>
                            </td>
                        <?php endif ?>
                    </tr>
                <?php endforeach ?>
                <?php if ($customers === []): ?><tr><td colspan="<?= $roleCode === 'OWNER' ? '8' : '7' ?>" class="muted">Belum ada pelanggan grosir.</td></tr><?php endif ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</div>
</div>
</body>
</html>
