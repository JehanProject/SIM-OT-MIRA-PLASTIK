<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Backup &amp; Restore - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/administration.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main class="admin-page">
    <section class="admin-heading"><div><p class="admin-eyebrow">PENGELOLAAN</p><h1>Backup &amp; Restore</h1><p>Buat salinan data, unduh file, atau pulihkan database dari backup aplikasi.</p></div><form method="post" action="<?= site_url('backup') ?>"><?= csrf_field() ?><button class="btn btn-primary" type="submit">Backup sekarang</button></form></section>
    <?php if ($success): ?><p class="alert alert-success" role="status"><?= esc($success) ?></p><?php endif ?>
    <?php if ($error): ?><p class="alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>
    <section class="admin-notice"><strong>Backup otomatis siap dijadwalkan</strong><span>Jalankan <code>php spark backup:create</code> setiap hari lewat Windows Task Scheduler atau cron. File disimpan di direktori privat aplikasi dan backup yang lebih lama dari 30 hari dibersihkan setiap kali perintah berjalan.</span></section>
    <section class="admin-panel">
        <div class="admin-panel-heading"><div><h2>File backup</h2><p>File hanya dapat diunduh oleh Owner yang sudah masuk.</p></div></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Nama file</th><th>Waktu</th><th>Ukuran</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($files as $file): ?><tr><td><strong><?= esc($file['name']) ?></strong></td><td><?= esc(date('d M Y · H:i', (int) $file['modified'])) ?></td><td><?= esc(number_format((float) $file['size'] / 1048576, 2, ',', '.')) ?> MB</td><td><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('backup/unduh/' . rawurlencode($file['name'])) ?>">Unduh</a></td></tr><?php endforeach ?>
            <?php if ($files === []): ?><tr><td colspan="4" class="admin-empty">Belum ada file backup.</td></tr><?php endif ?>
            </tbody>
        </table></div>
    </section>
    <section class="admin-panel admin-restore">
        <div class="admin-panel-heading"><div><h2>Restore database</h2><p>Restore mengganti seluruh isi database saat ini. Backup pengaman dibuat otomatis sebelum proses.</p></div></div>
        <form class="admin-form-grid" method="post" action="<?= site_url('backup/restore') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <label class="admin-form-wide">File backup JSON<input class="form-control" type="file" name="backup_file" accept="application/json,.json" required></label>
            <label>Ketik PULIHKAN untuk mengonfirmasi<input class="form-control" name="confirmation" autocomplete="off" required></label>
            <button class="btn btn-danger" type="submit">Pulihkan database</button>
        </form>
    </section>
</main>
</div>
</div>
</body>
</html>
