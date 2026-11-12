<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Kebijakan tenor bon - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/receivables-policy.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main>
    <div class="toolbar"><h1>Kebijakan tenor bon</h1><a class="button btn btn-secondary" href="<?= site_url($roleCode === 'KASIR' ? 'kasir' : 'piutang') ?>"><?= $roleCode === 'KASIR' ? 'Kembali ke kasir' : 'Kembali ke piutang' ?></a></div>
    <?php if ($success): ?><p class="notice alert alert-success" role="status"><?= esc($success) ?></p><?php endif ?>
    <?php if ($error): ?><p class="error alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>
    <section class="panel card">
        <h2>Kebijakan yang berlaku</h2>
        <?php if ($activePolicy): ?>
            <p>Maksimum tenor saat ini: <strong><?= esc($activePolicy['max_tenor_days']) ?> hari</strong>, berlaku sejak <?= esc($activePolicy['effective_from']) ?>.</p>
        <?php else: ?>
            <p class="error alert alert-warning">Belum ada kebijakan tenor. Bon grosir belum dapat diproses.</p>
        <?php endif ?>
        <form method="post" action="<?= site_url('piutang/kebijakan') ?>">
            <?= csrf_field() ?>
            <label for="max-tenor">Tenor maksimum baru (hari)</label>
            <input class="form-control" id="max-tenor" name="max_tenor_days" type="number" min="1" max="3650" step="1" required value="<?= esc((string) old('max_tenor_days', (string) ($activePolicy['max_tenor_days'] ?? ''))) ?>">
            <p class="muted">Kebijakan ini berlaku untuk seluruh toko mulai hari ini. Bon lama tetap merekam kebijakan tenor yang dipakai saat dibuat.</p>
            <button class="btn btn-primary" type="submit">Simpan kebijakan</button>
        </form>
    </section>
    <section class="panel card">
        <h2>Riwayat kebijakan</h2>
        <div class="table-wrap">
            <table class="table table-hover align-middle">
                <thead><tr><th>Tenor maksimum</th><th>Berlaku dari</th><th>Berlaku sampai</th><th>Ditetapkan oleh</th><th>Dibuat</th></tr></thead>
                <tbody>
                <?php foreach ($policies as $policy): ?>
                    <tr><td><?= esc($policy['max_tenor_days']) ?> hari</td><td><?= esc($policy['effective_from']) ?></td><td><?= esc($policy['effective_to'] ?? 'Berlaku') ?></td><td><?= esc($policy['full_name']) ?></td><td><?= esc($policy['created_at']) ?></td></tr>
                <?php endforeach ?>
                <?php if ($policies === []): ?><tr><td colspan="5" class="muted">Belum ada riwayat kebijakan.</td></tr><?php endif ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</div>
</div>
</body>
</html>
