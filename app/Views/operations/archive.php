<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Arsip Historis - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/operations.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main class="ops-page">
    <section class="ops-heading"><div><p class="ops-eyebrow">ARSIP</p><h1>Arsip Historis</h1><p class="ops-subtitle">Riwayat aktivitas tersimpan dan dapat ditelusuri tanpa mengubah data transaksi.</p></div></section>
    <section class="ops-panel">
        <form class="ops-filter" method="get" action="<?= site_url('arsip') ?>"><label>Dari tanggal<input class="form-control" type="date" name="from" value="<?= esc($from) ?>"></label><label>Sampai tanggal<input class="form-control" type="date" name="to" value="<?= esc($to) ?>"></label><button class="btn btn-primary" type="submit">Terapkan filter</button></form>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Waktu</th><th>Pengguna</th><th>Peran</th><th>Aktivitas</th><th>Modul</th><th>ID data</th><th>Rincian</th></tr></thead>
            <tbody>
            <?php foreach ($events as $event): ?>
                <?php $details = $event['after_data'] ?: $event['before_data']; ?>
                <tr><td><?= esc($event['created_at']) ?></td><td><?= esc($event['full_name']) ?></td><td><?= esc($event['role_name']) ?></td><td><?= esc($event['action']) ?></td><td><?= esc($event['entity_type']) ?></td><td><?= esc($event['entity_id'] ?? '—') ?></td><td><?php if ($details): ?><details><summary>Lihat</summary><pre class="ops-json"><?= esc($details) ?></pre></details><?php else: ?><span class="text-secondary">—</span><?php endif ?></td></tr>
            <?php endforeach ?>
            <?php if ($events === []): ?><tr><td colspan="7" class="ops-empty">Belum ada arsip aktivitas pada periode ini.</td></tr><?php endif ?>
            </tbody>
        </table></div>
        <p class="ops-footnote">Arsip ini bersifat baca-saja dan menampilkan maksimal 200 aktivitas terbaru.</p>
    </section>
</main>
</div>
</div>
</body>
</html>
