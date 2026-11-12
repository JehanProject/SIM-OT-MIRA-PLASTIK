<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Log Activity - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/administration.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main class="admin-page">
    <section class="admin-heading"><div><p class="admin-eyebrow">AUDIT</p><h1>Log Activity</h1><p>Jejak audit bersifat baca-saja dan tidak dapat diubah atau dihapus.</p></div></section>
    <section class="admin-panel">
        <form class="admin-search admin-search-wide" method="get" action="<?= site_url('aktivitas') ?>">
            <label>Dari tanggal<input class="form-control" type="date" name="from" value="<?= esc($from) ?>"></label>
            <label>Sampai tanggal<input class="form-control" type="date" name="to" value="<?= esc($to) ?>"></label>
            <label class="admin-search-query">Cari pengguna/aksi/modul<input class="form-control" type="search" name="q" value="<?= esc($search) ?>" placeholder="Masukkan kata kunci"></label>
            <button class="btn btn-primary" type="submit">Terapkan filter</button>
        </form>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Waktu</th><th>Pengguna</th><th>Peran</th><th>Aktivitas</th><th>Modul</th><th>ID data</th><th>Rincian</th></tr></thead>
            <tbody>
            <?php foreach ($events as $event): ?><?php $details = $event['after_data'] ?: $event['before_data']; ?><tr><td><?= esc($event['created_at']) ?></td><td><?= esc($event['full_name']) ?></td><td><?= esc($event['role_name']) ?></td><td><?= esc($event['action']) ?></td><td><?= esc($event['entity_type']) ?></td><td><?= esc($event['entity_id'] ?? '—') ?></td><td><?php if ($details): ?><details><summary>Lihat</summary><pre class="admin-json"><?= esc($details) ?></pre></details><?php else: ?>—<?php endif ?></td></tr><?php endforeach ?>
            <?php if ($events === []): ?><tr><td colspan="7" class="admin-empty">Belum ada aktivitas pada periode ini.</td></tr><?php endif ?>
            </tbody>
        </table></div>
        <p class="admin-footnote">Menampilkan maksimal 200 aktivitas terbaru.</p>
    </section>
</main>
</div>
</div>
</body>
</html>
