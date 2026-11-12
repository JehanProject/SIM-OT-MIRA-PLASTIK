<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Data Distributor - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/administration.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main class="admin-page">
    <section class="admin-heading">
        <div><p class="admin-eyebrow">PENGELOLAAN</p><h1>Data Distributor</h1><p>Kelola rekan pemasok barang Toko Mira Plastik.</p></div>
        <a class="btn btn-primary" href="#form-distributor">Tambah distributor</a>
    </section>
    <?php if ($success): ?><p class="alert alert-success" role="status"><?= esc($success) ?></p><?php endif ?>
    <?php if ($error): ?><p class="alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>
    <section class="admin-panel" id="form-distributor">
        <div class="admin-panel-heading"><div><h2>Tambah distributor</h2><p>Distributor baru langsung tersedia pada data penerimaan.</p></div></div>
        <form class="admin-form-grid" method="post" action="<?= site_url('distributor') ?>">
            <?= csrf_field() ?>
            <label>Nama distributor<input class="form-control" name="supplier_name" value="<?= esc(old('supplier_name')) ?>" maxlength="150" required></label>
            <label>Telepon<input class="form-control" name="phone" value="<?= esc(old('phone')) ?>" maxlength="40"></label>
            <label class="admin-form-wide">Alamat<textarea class="form-control" name="address" rows="2" maxlength="65535"><?= esc(old('address')) ?></textarea></label>
            <button class="btn btn-primary" type="submit">Simpan distributor</button>
        </form>
    </section>
    <section class="admin-panel">
        <div class="admin-panel-heading"><div><h2>Daftar distributor</h2><p>Riwayat penerimaan barang tetap tersimpan meskipun distributor dinonaktifkan.</p></div></div>
        <form class="admin-search" method="get" action="<?= site_url('distributor') ?>"><label class="visually-hidden" for="supplier-search">Cari distributor</label><input class="form-control" id="supplier-search" type="search" name="q" value="<?= esc($search) ?>" placeholder="Cari kode, nama, atau telepon"><button class="btn btn-outline-secondary" type="submit">Cari</button></form>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Kode</th><th>Nama distributor</th><th>Telepon</th><th>Alamat</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($suppliers as $supplier): ?><tr><td><?= esc($supplier['supplier_code']) ?></td><td><strong><?= esc($supplier['supplier_name']) ?></strong></td><td><?= esc($supplier['phone'] ?: '—') ?></td><td><?= esc($supplier['address'] ?: '—') ?></td><td><span class="admin-badge <?= $supplier['is_active'] ? 'is-active' : 'is-inactive' ?>"><?= $supplier['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td><td><?php if ($supplier['is_active']): ?><form method="post" action="<?= site_url('distributor/' . $supplier['supplier_id'] . '/nonaktifkan') ?>" onsubmit="return confirm('Nonaktifkan distributor ini?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" type="submit">Nonaktifkan</button></form><?php else: ?><span class="text-secondary">—</span><?php endif ?></td></tr><?php endforeach ?>
            <?php if ($suppliers === []): ?><tr><td colspan="6" class="admin-empty">Belum ada distributor terdaftar.</td></tr><?php endif ?>
            </tbody>
        </table></div>
        <p class="admin-footnote">Menampilkan maksimal 200 distributor.</p>
    </section>
</main>
</div>
</div>
</body>
</html>
