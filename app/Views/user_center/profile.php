<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Kelola Profil - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/user-center.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main class="user-center-page">
    <section class="user-center-heading">
        <div><p class="user-center-eyebrow">AKUN SAYA</p><h1>Kelola Profil</h1><p>Perbarui informasi akun dan kata sandi Anda.</p></div>
    </section>
    <?php if ($success): ?><p class="alert alert-success" role="status"><?= esc($success) ?></p><?php endif ?>
    <?php if ($error): ?><p class="alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>
    <section class="user-center-panel">
        <div class="user-center-panel-heading"><h2>Informasi akun</h2><span class="user-center-role"><?= esc($user['role_name']) ?></span></div>
        <form class="user-profile-form" method="post" action="<?= site_url('profil') ?>">
            <?= csrf_field() ?>
            <label>Nama lengkap<input class="form-control" name="full_name" value="<?= esc(old('full_name', $user['full_name'])) ?>" maxlength="150" required></label>
            <label>Username<input class="form-control" name="username" value="<?= esc(old('username', $user['username'])) ?>" minlength="3" maxlength="100" pattern="[A-Za-z0-9._\-]+" autocomplete="username" required></label>
            <div class="user-profile-divider"><h3>Ubah kata sandi</h3><p>Kosongkan jika tidak ingin mengganti kata sandi. Kata sandi baru minimal 6 karakter.</p></div>
            <label>Kata sandi saat ini<input class="form-control" name="current_password" type="password" autocomplete="current-password"></label>
            <label>Kata sandi baru<input class="form-control" name="new_password" type="password" minlength="6" maxlength="255" autocomplete="new-password"></label>
            <label>Konfirmasi kata sandi baru<input class="form-control" name="confirm_password" type="password" minlength="6" maxlength="255" autocomplete="new-password"></label>
            <div class="user-profile-actions"><button class="btn btn-primary" type="submit">Simpan profil</button></div>
        </form>
    </section>
</main>
</div>
</div>
</body>
</html>
