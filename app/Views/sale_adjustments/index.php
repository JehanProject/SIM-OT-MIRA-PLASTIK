<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= $this->include('layout/bootstrap_assets') ?>
    <title>Persetujuan Void/Retur - Mira Plastik</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/pages/sale-adjustments.css')) ?>">
</head>
<body>
<?= $this->include('layout/sidebar') ?>
<main>
    <div class="toolbar">
        <div>
            <h1>Persetujuan Void/Retur</h1>
            <p class="muted">Tinjau pengajuan, periksa refund tertunda, dan catat pengembalian uang yang telah dilakukan.</p>
        </div>
        <a class="button btn btn-secondary" href="<?= site_url('kasir') ?>">Kasir</a>
    </div>

    <?php if ($success): ?><p class="alert alert-success" role="status"><?= esc($success) ?></p><?php endif ?>
    <?php if ($error): ?><p class="alert alert-danger" role="alert"><?= esc($error) ?></p><?php endif ?>

    <section class="panel card">
        <h2>Daftar pengajuan</h2>
        <div class="table-wrap">
            <table class="table table-hover align-middle">
                <thead><tr><th>Faktur / waktu</th><th>Jenis / alasan</th><th>Pemohon</th><th>Status</th><th>Nilai retur</th><th>Tindakan / refund</th></tr></thead>
                <tbody>
                <?php foreach ($adjustments as $adjustment): ?>
                    <tr>
                        <td><a href="<?= site_url('kasir/struk/' . $adjustment['sale_id']) ?>"><?= esc($adjustment['invoice_number']) ?></a><br><span class="muted"><?= esc($adjustment['requested_at']) ?></span></td>
                        <td>
                            <?= esc($adjustment['adjustment_type']) ?><br>
                            <?= nl2br(esc($adjustment['reason'])) ?>
                            <ul class="adjustment-items">
                                <?php foreach ($adjustment['details'] as $detail): ?>
                                    <li>
                                        <?= esc($detail['product_code_snapshot']) ?> · <?= esc($detail['product_name_snapshot']) ?>:
                                        <?= esc(format_quantity($detail['quantity'], 6)) ?> <?= esc($detail['unit_snapshot']) ?>
                                        → <?= esc($detail['location_name']) ?> · Rp <?= esc(number_format((float) $detail['refund_amount'], 2, ',', '.')) ?>
                                    </li>
                                <?php endforeach ?>
                            </ul>
                        </td>
                        <td><?= esc($adjustment['requester_name']) ?></td>
                        <td><?= esc($adjustment['status']) ?><?= $adjustment['approver_name'] ? '<br><span class="muted">' . esc($adjustment['approver_name']) . '</span>' : '' ?></td>
                        <td>Rp <?= esc(number_format((float) $adjustment['refund_amount'], 2, ',', '.')) ?></td>
                        <td>
                            <?php if ($adjustment['status'] === 'PENDING'): ?>
                                <form class="decision-form" method="post" action="<?= site_url('persetujuan/' . $adjustment['sale_adjustment_id'] . '/keputusan') ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-primary" name="decision" value="APPROVED" type="submit">Setujui</button>
                                    <label class="form-label" for="decision-reason-<?= esc($adjustment['sale_adjustment_id']) ?>">Alasan jika ditolak</label>
                                    <input class="form-control" id="decision-reason-<?= esc($adjustment['sale_adjustment_id']) ?>" name="decision_reason" maxlength="1000" placeholder="Wajib diisi untuk penolakan">
                                    <button class="btn btn-outline-danger" name="decision" value="REJECTED" type="submit">Tolak</button>
                                </form>
                            <?php elseif ($adjustment['status'] === 'APPROVED' && (float) $adjustment['pending_refund'] > 0): ?>
                                <strong class="refund-pending">Refund tertunda: Rp <?= esc(number_format((float) $adjustment['pending_refund'], 2, ',', '.')) ?></strong>
                                <?php if ($accounts !== []): ?>
                                    <form class="refund-form" method="post" action="<?= site_url('persetujuan/' . $adjustment['sale_adjustment_id'] . '/refund') ?>">
                                        <?= csrf_field() ?>
                                        <label class="form-label" for="method-<?= esc($adjustment['sale_adjustment_id']) ?>">Metode refund yang benar-benar digunakan</label>
                                        <select class="form-select" id="method-<?= esc($adjustment['sale_adjustment_id']) ?>" name="payment_method" required>
                                            <option value="TUNAI">Tunai</option>
                                            <option value="TRANSFER">Transfer</option>
                                        </select>
                                        <label class="form-label" for="account-<?= esc($adjustment['sale_adjustment_id']) ?>">Akun kas/bank</label>
                                        <select class="form-select" id="account-<?= esc($adjustment['sale_adjustment_id']) ?>" name="cash_account_id" required>
                                            <?php foreach ($accounts as $account): ?>
                                                <option value="<?= esc($account['cash_account_id']) ?>" data-type="<?= esc($account['account_type']) ?>">
                                                    <?= esc($account['account_name']) ?> (<?= esc($account['account_type']) ?>)
                                                </option>
                                            <?php endforeach ?>
                                        </select>
                                        <button class="btn btn-secondary" type="submit">Catat refund penuh</button>
                                    </form>
                                <?php else: ?>
                                    <p class="muted">Akun kas/bank aktif belum tersedia.</p>
                                <?php endif ?>
                                <p class="muted">Catat hanya setelah uang benar-benar dikembalikan. Refund dicatat satu kali penuh.</p>
                            <?php elseif ($adjustment['status'] === 'APPROVED' && $adjustment['payment_method'] === 'BON' && (float) $adjustment['refund_amount'] > 0 && (float) $adjustment['refund_amount'] <= (float) $adjustment['adjusted_amount']): ?>
                                <span class="refund-recorded">Nilai retur telah dikoreksi terhadap piutang; tidak ada refund tertunda.</span>
                            <?php elseif ($adjustment['status'] === 'APPROVED' && (float) $adjustment['refund_amount'] > 0): ?>
                                <span class="refund-recorded">Refund telah tercatat</span>
                            <?php else: ?>
                                <span class="muted">Tidak ada tindakan tertunda.</span>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($adjustments === []): ?><tr><td colspan="6" class="muted">Belum ada pengajuan Void/Retur.</td></tr><?php endif ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
