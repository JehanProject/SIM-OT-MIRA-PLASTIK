<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Kelola Akun Pengguna - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/administration.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main class="admin-page">
    <section class="admin-heading"><div><p class="admin-eyebrow">PENGELOLAAN</p><h1>Kelola Akun Pengguna</h1><p>Atur akun dan hak akses pengguna sistem.</p></div><a class="btn btn-primary" href="#form-user">Buat akun</a></section>
    <?php if ($success): ?><p class="alert alert-success" role="status"><?= esc($success) ?></p><?php endif ?>
    <?php if ($error): ?><p class="alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>
    <section class="admin-panel" id="form-user">
        <div class="admin-panel-heading"><div><h2>Buat akun baru</h2><p>Kata sandi awal minimal 6 karakter. Berikan kata sandi kepada pengguna melalui saluran aman.</p></div></div>
        <form class="admin-form-grid" method="post" action="<?= site_url('pengguna') ?>">
            <?= csrf_field() ?>
            <label>Nama pengguna<input class="form-control" name="full_name" value="<?= esc(old('full_name')) ?>" maxlength="150" required></label>
            <label>Peran<select class="form-select" name="role_code" required><?php foreach ($roles as $role): ?><option value="<?= esc($role['role_code']) ?>" <?= old('role_code') === $role['role_code'] ? 'selected' : '' ?>><?= esc($role['role_name']) ?></option><?php endforeach ?></select></label>
            <label>Username<input class="form-control" name="username" value="<?= esc(old('username')) ?>" minlength="3" maxlength="100" pattern="[A-Za-z0-9._\-]+" autocomplete="off" required></label>
            <label>Password awal<input class="form-control" name="password" type="password" minlength="6" maxlength="255" autocomplete="new-password" required></label>
            <button class="btn btn-primary" type="submit">Simpan akun</button>
        </form>
    </section>
    <section class="admin-panel">
        <div class="admin-panel-heading"><div><h2>Daftar akun</h2><p>Akses pengguna diperiksa kembali pada setiap permintaan.</p></div></div>
        <form class="admin-search" method="get" action="<?= site_url('pengguna') ?>"><label class="visually-hidden" for="user-search">Cari pengguna</label><input class="form-control" id="user-search" type="search" name="q" value="<?= esc($search) ?>" placeholder="Cari nama, username, atau peran"><button class="btn btn-outline-secondary" type="submit">Cari</button></form>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>ID</th><th>Nama</th><th>Username</th><th>Peran</th><th>Login terakhir</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td>USR-<?= esc(str_pad((string) $user['user_id'], 3, '0', STR_PAD_LEFT)) ?></td>
                    <td><strong><?= esc($user['full_name']) ?></strong></td>
                    <td><?= esc($user['username']) ?></td>
                    <td><?= esc($user['role_name']) ?></td>
                    <td><?= esc($user['last_login_at'] ?: 'Belum pernah') ?></td>
                    <td><span class="admin-badge <?= $user['is_active'] ? 'is-active' : 'is-inactive' ?>"><?= $user['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                    <td>
                        <div class="admin-user-actions">
                            <a class="btn btn-sm btn-outline-primary" href="<?= site_url('pengguna/' . $user['user_id'] . '/edit') ?>">Edit</a>
                            <?php if ($user['is_active'] && (int) $user['user_id'] !== $currentUserId): ?>
                                <form method="post" action="<?= site_url('pengguna/' . $user['user_id'] . '/nonaktifkan') ?>" data-confirm="Nonaktifkan akun ini?">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Nonaktifkan</button>
                                </form>
                            <?php endif ?>
                            <?php if ((int) $user['user_id'] !== $currentUserId): ?>
                                <form method="post" action="<?= site_url('pengguna/' . $user['user_id'] . '/hapus') ?>" data-confirm="Hapus permanen akun ini? Penghapusan hanya bisa dilakukan jika tidak ada transaksi atau catatan yang merujuk ke akun tersebut.">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                                </form>
                            <?php endif ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach ?>
            <?php if ($users === []): ?><tr><td colspan="7" class="admin-empty">Belum ada akun pengguna.</td></tr><?php endif ?>
            </tbody>
        </table></div>
    </section>
</main>
</div>
</div>
</body>
</html>
