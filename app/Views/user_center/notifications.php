<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Notifikasi - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/user-center.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main class="user-center-page">
    <section class="user-center-heading">
        <div><p class="user-center-eyebrow">PUSAT INFORMASI</p><h1>Notifikasi</h1><p>Informasi operasional yang sesuai dengan peran Anda.</p></div>
        <?php if ($unreadCount > 0): ?>
            <form method="post" action="<?= site_url('notifikasi/baca-semua') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline-secondary" type="submit">Tandai semua dibaca</button>
            </form>
        <?php endif ?>
    </section>
    <?php if ($success): ?><p class="alert alert-success" role="status"><?= esc($success) ?></p><?php endif ?>
    <?php if ($error): ?><p class="alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>
    <section class="user-center-panel" aria-label="Daftar notifikasi">
        <div class="user-center-panel-heading"><h2>Terbaru</h2><span class="user-center-role"><?= esc((string) $unreadCount) ?> belum dibaca</span></div>
        <div class="notification-list">
            <?php foreach ($notifications as $notification): ?>
                <article class="notification-item <?= $notification['is_read'] ? 'is-read' : 'is-unread' ?>">
                    <span class="notification-status" aria-hidden="true"></span>
                    <div class="notification-copy">
                        <div class="notification-title-row">
                            <h3><?= esc($notification['title']) ?></h3>
                            <time datetime="<?= esc(date('c', strtotime($notification['created_at']))) ?>"><?= esc(date('d/m/Y H:i', strtotime($notification['created_at']))) ?></time>
                        </div>
                        <p><?= esc($notification['message']) ?></p>
                        <div class="notification-actions">
                            <?php if ($notification['target_url']): ?><a class="btn btn-sm btn-outline-primary" href="<?= esc(site_url($notification['target_url'])) ?>">Lihat detail</a><?php endif ?>
                            <?php if (! (bool) $notification['is_read']): ?>
                                <form method="post" action="<?= site_url('notifikasi/' . $notification['notification_id'] . '/baca') ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Tandai dibaca</button>
                                </form>
                            <?php endif ?>
                        </div>
                    </div>
                </article>
            <?php endforeach ?>
            <?php if ($notifications === []): ?><p class="notification-empty">Belum ada notifikasi. Informasi baru akan muncul di sini sesuai tugas dan hak akses Anda.</p><?php endif ?>
        </div>
        <?php if (count($notifications) >= 100): ?><p class="admin-footnote">Menampilkan 100 notifikasi terbaru.</p><?php endif ?>
    </section>
</main>
</div>
</div>
</body>
</html>
