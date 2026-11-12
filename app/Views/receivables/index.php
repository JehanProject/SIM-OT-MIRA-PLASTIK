<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Piutang - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/receivables-index.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main>
    <div class="toolbar">
        <div>
            <h1>Piutang pelanggan</h1>
            <p class="muted">Bon grosir, sisa tagihan, jatuh tempo, serta pembayaran cicilan/pelunasan.</p>
        </div>
        <a class="button btn btn-secondary" href="<?= site_url('kasir') ?>">Kasir</a>
        <?php if ($roleCode === 'OWNER'): ?>
            <a class="button btn btn-secondary" href="<?= site_url('piutang/pelanggan') ?>">Data pelanggan</a>
            <a class="button btn btn-secondary" href="<?= site_url('piutang/kebijakan') ?>">Kebijakan tenor</a>
        <?php endif ?>
    </div>

    <?php if ($success): ?><p class="notice alert alert-success" role="status"><?= esc($success) ?></p><?php endif ?>
    <?php if ($error): ?><p class="error alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>
    <?php if ($accounts === []): ?><p class="error alert alert-danger" role="alert">Akun kas/bank aktif belum tersedia; pembayaran piutang belum dapat dicatat.</p><?php endif ?>

    <section class="panel card reminders" aria-labelledby="reminders-title">
        <h2 id="reminders-title">Pengingat jatuh tempo</h2>
        <p class="muted">Tagihan terlambat, jatuh tempo hari ini, atau jatuh tempo sampai <?= esc(date('d/m/Y', strtotime($reminderCutoff))) ?>.</p>
        <?php if ($reminders['overdue'] === [] && $reminders['due_today'] === [] && $reminders['upcoming'] === []): ?>
            <p class="muted" role="status">Tidak ada tagihan yang perlu ditindaklanjuti dalam periode ini.</p>
        <?php else: ?>
            <?php foreach ([
                'overdue' => 'Terlambat',
                'due_today' => 'Jatuh tempo hari ini',
                'upcoming' => 'Jatuh tempo dalam 7 hari',
            ] as $reminderType => $reminderLabel): ?>
                <?php if ($reminders[$reminderType] !== []): ?>
                    <div class="reminder-group <?= $reminderType === 'overdue' ? 'overdue' : '' ?>">
                        <h3><?= esc($reminderLabel) ?> (<?= count($reminders[$reminderType]) ?>)</h3>
                        <ul>
                            <?php foreach ($reminders[$reminderType] as $reminder): ?>
                                <li>
                                    <a href="#receivable-<?= esc($reminder['receivable_id']) ?>">
                                        <?= esc($reminder['customer_name']) ?> · <?= esc($reminder['invoice_number'] ?? 'Saldo piutang awal') ?>
                                    </a>
                                    — <?= esc(date('d/m/Y', strtotime($reminder['due_date']))) ?> · Sisa Rp <?= esc(number_format((float) $reminder['outstanding_amount'], 2, ',', '.')) ?>
                                </li>
                            <?php endforeach ?>
                        </ul>
                    </div>
                <?php endif ?>
            <?php endforeach ?>
        <?php endif ?>
    </section>

    <section class="panel card">
        <h2>Tagihan yang masih terbuka</h2>
        <div class="table-wrap">
            <table class="table table-hover align-middle">
                <thead><tr><th>Pelanggan</th><th>Faktur</th><th>Total bon</th><th>Sudah dibayar</th><th>Sisa tagihan</th><th>Jatuh tempo</th><th>Status</th><th>Catat pembayaran</th></tr></thead>
                <tbody>
                <?php foreach ($receivables as $receivable): ?>
                    <?php $isOverdue = $receivable['due_date'] !== null && $receivable['due_date'] < date('Y-m-d'); ?>
                    <tr id="receivable-<?= esc($receivable['receivable_id']) ?>">
                        <td><?= esc($receivable['customer_code']) ?> · <?= esc($receivable['customer_name']) ?><?= $receivable['business_name'] ? '<br><span class="muted">' . esc($receivable['business_name']) . '</span>' : '' ?></td>
                        <td><?= esc($receivable['invoice_number'] ?? 'Saldo piutang awal') ?></td>
                        <td>Rp <?= esc(number_format((float) $receivable['original_amount'], 2, ',', '.')) ?></td>
                        <td>Rp <?= esc(number_format((float) $receivable['paid_amount'], 2, ',', '.')) ?></td>
                        <td><strong>Rp <?= esc(number_format((float) $receivable['outstanding_amount'], 2, ',', '.')) ?></strong></td>
                        <td class="<?= $isOverdue ? 'overdue' : '' ?>"><?= esc($receivable['due_date'] ?? 'Tidak ditentukan') ?><?= $isOverdue ? ' · Terlambat' : '' ?></td>
                        <td><?= esc($receivable['status']) ?></td>
                        <td>
                            <?php if ($accounts !== []): ?>
                                <form class="payment-form" method="post" action="<?= site_url('piutang/' . $receivable['receivable_id'] . '/pembayaran') ?>">
                                    <?= csrf_field() ?>
                                    <input class="form-control" name="amount" type="number" min="0.01" max="<?= esc($receivable['outstanding_amount']) ?>" step="0.01" placeholder="Jumlah Rp" required aria-label="Jumlah pembayaran">
                                    <select class="form-select" name="payment_method" required aria-label="Metode pembayaran">
                                        <option value="TUNAI">Tunai</option>
                                        <option value="TRANSFER">Transfer</option>
                                    </select>
                                    <select class="form-select" name="cash_account_id" required aria-label="Akun kas/bank">
                                        <?php foreach ($accounts as $account): ?>
                                            <option value="<?= esc($account['cash_account_id']) ?>" data-type="<?= esc($account['account_type']) ?>">
                                                <?= esc($account['account_name']) ?>
                                            </option>
                                        <?php endforeach ?>
                                    </select>
                                    <button class="btn btn-primary" type="submit">Catat pembayaran</button>
                                </form>
                            <?php else: ?><span class="muted">Akun belum tersedia</span><?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($receivables === []): ?><tr><td colspan="8" class="muted">Belum ada bon atau saldo piutang yang terbuka.</td></tr><?php endif ?>
                </tbody>
            </table>
        </div>
    </section>
    <section class="panel card">
        <h2>Riwayat pembayaran terbaru</h2>
        <div class="table-wrap">
            <table class="table table-hover align-middle">
                <thead><tr><th>Waktu</th><th>Pelanggan</th><th>Faktur</th><th>Jumlah</th><th>Metode</th><th>Akun</th><th>Dicatat oleh</th></tr></thead>
                <tbody>
                <?php foreach ($recentPayments as $payment): ?>
                    <tr>
                        <td><?= esc($payment['paid_at']) ?></td>
                        <td><?= esc($payment['customer_name']) ?></td>
                        <td><?= esc($payment['invoice_number'] ?? 'Saldo piutang awal') ?></td>
                        <td>Rp <?= esc(number_format((float) $payment['amount'], 2, ',', '.')) ?></td>
                        <td><?= esc($payment['payment_method']) ?></td>
                        <td><?= esc($payment['account_name']) ?></td>
                        <td><?= esc($payment['full_name']) ?></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($recentPayments === []): ?><tr><td colspan="7" class="muted">Belum ada pembayaran piutang yang dicatat.</td></tr><?php endif ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</div>
</div>
<script src="<?= esc(base_url('assets/js/pages/receivables.js')) ?>" defer></script>
</body>
</html>
