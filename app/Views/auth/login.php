<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Masuk - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/login.css')) ?>">
</head>
<body class="login-page">
<main class="login-card">
    <header class="login-header">
        <div class="login-brand-mark" aria-hidden="true">M</div>
        <div class="login-brand-copy">
            <strong>Toko Mira Plastik</strong>
            <span>Operasional Terintegrasi</span>
        </div>
    </header>

    <section class="login-content" aria-labelledby="login-title">
        <h1 id="login-title">Masuk ke akun</h1>
        <p class="login-description">Gunakan akun yang diberikan oleh pemilik toko.</p>

        <?php if (isset($error) && $error !== null): ?>
            <div class="login-notice login-notice-error" role="alert"><?= esc($error) ?></div>
        <?php endif ?>
        <?php if ($message = session()->getFlashdata('message')): ?>
            <div class="login-notice login-notice-info" role="status"><?= esc($message) ?></div>
        <?php endif ?>

        <form class="login-form" method="post" action="<?= site_url('login') ?>">
            <?= csrf_field() ?>
            <div class="login-field">
                <label for="username">Username</label>
                <div class="login-input-wrap">
                    <svg class="login-field-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="8" r="3.5"></circle>
                        <path d="M5 20c.7-3.2 3.1-5 7-5s6.3 1.8 7 5"></path>
                    </svg>
                    <input id="username" name="username" type="text" autocomplete="username" placeholder="Masukkan username" required autofocus>
                </div>
            </div>

            <div class="login-field">
                <label for="password">Password</label>
                <div class="login-input-wrap">
                    <svg class="login-field-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <rect x="5" y="10" width="14" height="11" rx="2"></rect>
                        <path d="M8 10V7a4 4 0 1 1 8 0v3"></path>
                        <path d="M12 14v3"></path>
                    </svg>
                    <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Masukkan password" required>
                    <button class="login-password-toggle" type="button" aria-label="Tampilkan password" aria-pressed="false" data-password-toggle>
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M2.5 12s3.3-6 9.5-6 9.5 6 9.5 6-3.3 6-9.5 6-9.5-6-9.5-6Z"></path>
                            <circle cx="12" cy="12" r="2.5"></circle>
                        </svg>
                    </button>
                </div>
            </div>

            <p class="login-session-note">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M12 7v5l3 2"></path>
                </svg>
                Akses hanya untuk pengguna yang terdaftar.
            </p>

            <button class="login-submit" type="submit">
                Masuk
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M5 12h14m-6-6 6 6-6 6"></path>
                </svg>
            </button>
        </form>
    </section>

    <footer class="login-footer">© <?= date('Y') ?> Toko Mira Plastik</footer>
</main>
<script>
    document.querySelector('.login-form')?.addEventListener('submit', function () {
        sessionStorage.removeItem('app-sidebar-scroll-top');
    });
    document.querySelector('[data-password-toggle]')?.addEventListener('click', function () {
        const password = document.getElementById('password');
        const isVisible = password.type === 'text';
        password.type = isVisible ? 'password' : 'text';
        this.setAttribute('aria-pressed', String(!isVisible));
        this.setAttribute('aria-label', isVisible ? 'Tampilkan password' : 'Sembunyikan password');
    });
</script>
</body>
</html>
