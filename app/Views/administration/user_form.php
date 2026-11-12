<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Edit Akun Pengguna - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/administration.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main class="admin-page">
    <section class="admin-heading">
        <div><p class="admin-eyebrow">PENGELOLAAN</p><h1>Edit Akun Pengguna</h1><p>Perbarui nama, username, peran, atau kata sandi akun.</p></div>
        <a class="btn btn-outline-secondary" href="<?= site_url('pengguna') ?>">Kembali ke daftar</a>
    </section>
    <?php if ($error): ?><p class="alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>
    <section class="admin-panel">
        <form class="admin-form-grid" method="post" action="<?= site_url('pengguna/' . $user['user_id'] . '/update') ?>">
            <?= csrf_field() ?>
            <label>Nama pengguna<input class="form-control" name="full_name" value="<?= esc(old('full_name', $user['full_name'])) ?>" maxlength="150" required></label>
            <label>Peran
                <select class="form-select" name="role_code" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= esc($role['role_code']) ?>" <?= old('role_code', $user['role_code']) === $role['role_code'] ? 'selected' : '' ?>><?= esc($role['role_name']) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <label>Username<input class="form-control" name="username" value="<?= esc(old('username', $user['username'])) ?>" minlength="3" maxlength="100" pattern="[A-Za-z0-9._\-]+" autocomplete="off" required></label>
            <label>Password baru (minimal 6 karakter; kosongkan jika tidak diubah)<input class="form-control" name="password" type="password" minlength="6" maxlength="255" autocomplete="new-password"></label>
            <div class="admin-user-form-actions">
                <button class="btn btn-primary" type="submit">Simpan perubahan</button>
                <a class="btn btn-outline-secondary" href="<?= site_url('pengguna') ?>">Batal</a>
            </div>
        </form>
    </section>
</main>
</div>
</div>
</body>
</html>
