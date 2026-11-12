<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Arus Kas - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/operations.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main class="ops-page">
    <section class="ops-heading">
        <div><p class="ops-eyebrow">KEUANGAN</p><h1>Arus Kas</h1><p class="ops-subtitle">Pemasukan dan pengeluaran yang tercatat di ledger kas/bank.</p></div>
    </section>
    <?php if ($success): ?><p class="alert alert-success" role="status"><?= esc($success) ?></p><?php endif ?>
    <?php if ($error): ?><p class="alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>
    <?php foreach ($formErrors as $message): ?><p class="alert alert-danger" role="alert"><?= esc($message) ?></p><?php endforeach ?>
    <section class="ops-metrics">
        <article class="ops-metric"><span>Transaksi tercatat</span><strong><?= esc(number_format((int) $summary['entry_count'], 0, ',', '.')) ?></strong><small>Dalam periode terpilih</small></article>
        <article class="ops-metric"><span>Total pemasukan</span><strong>Rp <?= esc(number_format((float) $summary['inflow'], 0, ',', '.')) ?></strong><small><?= esc($from) ?> – <?= esc($to) ?></small></article>
        <article class="ops-metric"><span>Total pengeluaran</span><strong>Rp <?= esc(number_format((float) $summary['outflow'], 0, ',', '.')) ?></strong><small>Saldo bersih Rp <?= esc(number_format((float) $summary['inflow'] - (float) $summary['outflow'], 0, ',', '.')) ?></small></article>
    </section>
    <details class="ops-panel ops-form-panel">
        <summary class="ops-panel-heading"><div><h2>Catat transaksi kas</h2><p class="ops-subtitle">Pencatatan manual langsung memperbarui ledger akun.</p></div><span class="btn btn-primary">Tambah transaksi</span></summary>
        <form class="ops-cash-form" method="post" action="<?= site_url('arus-kas') ?>">
            <?= csrf_field() ?>
            <label>Jenis transaksi<select class="form-select" name="direction" required><option value="MASUK">Pemasukan</option><option value="KELUAR">Pengeluaran</option></select></label>
            <label>Kategori<input class="form-control" name="category" maxlength="100" placeholder="Contoh: Pemasukan lain-lain" required></label>
            <label>Nominal<input class="form-control" name="amount" type="number" min="0.01" step="0.01" required></label>
            <label>Tanggal<input class="form-control" name="occurred_at" type="datetime-local" value="<?= esc(date('Y-m-d\TH:i')) ?>" required></label>
            <label>Akun kas/bank<select class="form-select" name="cash_account_id" required><?php foreach ($accounts as $account): ?><option value="<?= esc($account['cash_account_id']) ?>"><?= esc($account['account_name']) ?></option><?php endforeach ?></select></label>
            <label class="ops-cash-description">Keterangan<input class="form-control" name="description" maxlength="255" placeholder="Jelaskan transaksi kas" required></label>
            <button class="btn btn-primary" type="submit" <?= $accounts === [] ? 'disabled' : '' ?>>Simpan transaksi</button>
            <?php if ($accounts === []): ?><p class="text-danger">Belum ada akun kas/bank aktif.</p><?php endif ?>
        </form>
    </details>
    <section class="ops-panel">
        <div class="ops-panel-heading"><div><h2>Transaksi kas</h2><p class="ops-subtitle">Pemasukan dan pengeluaran menurut ledger kas/bank.</p></div></div>
        <form class="ops-filter" method="get" action="<?= site_url('arus-kas') ?>">
            <label>Dari tanggal<input class="form-control" type="date" name="from" value="<?= esc($from) ?>"></label><label>Sampai tanggal<input class="form-control" type="date" name="to" value="<?= esc($to) ?>"></label><button class="btn btn-outline-secondary" type="submit">Terapkan periode</button>
        </form>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Tanggal</th><th>Jenis</th><th>Kategori</th><th>Keterangan</th><th>Akun</th><th>Dicatat oleh</th><th>Nominal</th></tr></thead>
            <tbody>
            <?php foreach ($movements as $movement): ?><tr><td><?= esc($movement['occurred_at']) ?></td><td><span class="ops-status <?= $movement['direction'] === 'MASUK' ? 'is-positive' : 'is-neutral' ?>"><?= $movement['direction'] === 'MASUK' ? 'Pemasukan' : 'Pengeluaran' ?></span></td><td><?= esc($movement['category'] ?: $movement['payment_method']) ?></td><td><?= esc($movement['notes'] ?: $movement['invoice_number']) ?></td><td><?= esc($movement['account_name']) ?></td><td><?= esc($movement['full_name']) ?></td><td>Rp <?= esc(number_format((float) $movement['amount'], 0, ',', '.')) ?></td></tr><?php endforeach ?>
            <?php if ($movements === []): ?><tr><td colspan="7" class="ops-empty">Belum ada transaksi kas pada periode ini.</td></tr><?php endif ?>
            </tbody>
        </table></div>
    </section>
</main>
</div>
</div>
</body>
</html>
